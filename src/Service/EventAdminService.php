<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\EventAdminRepositoryInterface;
use BSO\Survival\Database\Repository\EventPublicationRepositoryInterface;
use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Database\Repository\PartAdminRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class EventAdminService {
    /** @var EventRepositoryInterface */
    private $events;

    /** @var EventAdminRepositoryInterface */
    private $eventAdmin;

    /** @var PartAdminRepositoryInterface */
    private $parts;

    /** @var EventPublicationRepositoryInterface */
    private $publications;

    public function __construct(
        EventRepositoryInterface $events,
        EventAdminRepositoryInterface $eventAdmin,
        PartAdminRepositoryInterface $parts,
        EventPublicationRepositoryInterface $publications
    ) {
        $this->events = $events;
        $this->eventAdmin = $eventAdmin;
        $this->parts = $parts;
        $this->publications = $publications;
    }

    /** @return object */
    public function createEvent(string $name, string $eventDate, int $maxTeams = 22) {
        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new InvalidArgumentException('event_name is verplicht.');
        }

        $cleanDate = trim($eventDate);
        if (!$this->isValidDate($cleanDate)) {
            throw new InvalidArgumentException('event_date moet YYYY-MM-DD zijn.');
        }

        if ($maxTeams <= 0) {
            throw new InvalidArgumentException('max_teams moet groter zijn dan 0.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $metaData = json_encode(['max_teams' => $maxTeams], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metaData === false) {
            throw new RuntimeException('meta_data kon niet worden opgebouwd.');
        }

        $created = $this->eventAdmin->create([
            'name' => $cleanName,
            'event_date' => $cleanDate,
            'status' => 'concept',
            'meta_data' => $metaData,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($created === null) {
            throw new RuntimeException('Event kon niet worden aangemaakt.');
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $options
     * @return array{event: object, summary: array<string, int>}
     */
    public function createEventWithSetup(string $name, string $eventDate, int $maxTeams = 22, array $options = []): array {
        $createDemoTeams = !empty($options['create_demo_teams']);
        $demoTeamsCount = isset($options['demo_teams_count']) ? (int) $options['demo_teams_count'] : 22;
        $linkAllParts = !empty($options['link_all_parts']);
        $generateScores = !empty($options['generate_scores']);

        if ($createDemoTeams && $demoTeamsCount <= 0) {
            throw new InvalidArgumentException('demo_teams_count moet groter zijn dan 0.');
        }

        if ($createDemoTeams && $demoTeamsCount > $maxTeams) {
            throw new InvalidArgumentException('Aantal demo teams mag niet groter zijn dan max_teams.');
        }

        $created = $this->createEvent($name, $eventDate, $maxTeams);
        $eventId = (int) ($created->id ?? 0);
        $summary = [
            'teams_created' => 0,
            'parts_linked' => 0,
            'part_rules_created' => 0,
            'timeslots_created' => 0,
            'assignments_created' => 0,
            'scores_created' => 0,
        ];

        if ($createDemoTeams) {
            $summary['teams_created'] = $this->createDemoTeamsForEvent($eventId, $demoTeamsCount);
        }

        if ($linkAllParts) {
            $eligibleParts = $this->listEligiblePartsForEvent($eventId);
            $partIds = array_values(array_filter(array_map(static function ($part): int {
                return (int) ($part->id ?? 0);
            }, $eligibleParts)));

            if ($partIds !== []) {
                $result = $this->linkPartsToEvent($eventId, $partIds);
                $summary['parts_linked'] = (int) ($result['linked_count'] ?? 0);
                $summary['part_rules_created'] += $this->ensureDefaultPartRulesForPartIds($partIds);
            }
        }

        if ($generateScores) {
            if (!$linkAllParts) {
                $assignedPartIds = array_values(array_filter(array_map(static function ($part): int {
                    return (int) ($part->id ?? 0);
                }, $this->listAssignedPartsForEvent($eventId))));
                $summary['part_rules_created'] += $this->ensureDefaultPartRulesForPartIds($assignedPartIds);
            }

            $schedule = $this->generateDemoScheduleForEvent($eventId, $eventDate);
            $summary['timeslots_created'] = (int) ($schedule['timeslots_created'] ?? 0);
            $summary['assignments_created'] = (int) ($schedule['assignments_created'] ?? 0);
            $summary['scores_created'] = $this->createInitialScoresForAssignments($schedule['assignment_ids'] ?? []);
        }

        return [
            'event' => $created,
            'summary' => $summary,
        ];
    }

    /** @return object */
    public function updateEvent(int $eventId, string $name, string $eventDate, int $maxTeams = 22) {
        $event = $this->requireEditableEvent($eventId);

        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new InvalidArgumentException('event_name is verplicht.');
        }

        $cleanDate = trim($eventDate);
        if (!$this->isValidDate($cleanDate)) {
            throw new InvalidArgumentException('event_date moet YYYY-MM-DD zijn.');
        }

        if ($maxTeams <= 0) {
            throw new InvalidArgumentException('max_teams moet groter zijn dan 0.');
        }

        $metaData = json_encode(['max_teams' => $maxTeams], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metaData === false) {
            throw new RuntimeException('meta_data kon niet worden opgebouwd.');
        }

        $updated = $this->eventAdmin->updateById((int) ($event->id ?? $eventId), [
            'name' => $cleanName,
            'event_date' => $cleanDate,
            'meta_data' => $metaData,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($updated === null) {
            throw new RuntimeException('Event kon niet worden bijgewerkt.');
        }

        return $updated;
    }

    /**
     * @return array{timeslots_created: int, assignments_created: int, scores_created: int, deleted_scores: int, deleted_assignments: int, deleted_timeslots: int}
     */
    public function generatePlanningAndScoresForEvent(int $eventId): array {
        $event = $this->requireEditableEvent($eventId);
        $eventDate = (string) ($event->event_date ?? '');

        $assignedParts = $this->listAssignedPartsForEvent($eventId);
        if ($assignedParts === []) {
            throw new RuntimeException('Planning + score-records genereren vereist gekoppelde onderdelen.');
        }

        $partIds = array_values(array_filter(array_map(static function ($part): int {
            return (int) ($part->id ?? 0);
        }, $assignedParts)));
        $this->ensureDefaultPartRulesForPartIds($partIds);

        $cleanup = $this->purgeScoreDataForEvent($eventId);
        $schedule = $this->generateDemoScheduleForEvent($eventId, $eventDate);
        $scoresCreated = $this->createInitialScoresForAssignments($schedule['assignment_ids'] ?? []);

        return [
            'timeslots_created' => (int) ($schedule['timeslots_created'] ?? 0),
            'assignments_created' => (int) ($schedule['assignments_created'] ?? 0),
            'scores_created' => $scoresCreated,
            'deleted_scores' => (int) ($cleanup['deleted_scores'] ?? 0),
            'deleted_assignments' => (int) ($cleanup['deleted_assignments'] ?? 0),
            'deleted_timeslots' => (int) ($cleanup['deleted_timeslots'] ?? 0),
        ];
    }

    /**
     * @return array{timeslots: int, assignments: int, scores: int}
     */
    public function getPlanningScoreSnapshot(int $eventId): array {
        if ($eventId <= 0) {
            return [
                'timeslots' => 0,
                'assignments' => 0,
                'scores' => 0,
            ];
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return [
                'timeslots' => 0,
                'assignments' => 0,
                'scores' => 0,
            ];
        }

        $timeslotsTable = $wpdb->prefix . 'bso_survival_timeslots';
        $assignmentsTable = $wpdb->prefix . 'bso_survival_assignments';
        $scoresTable = $wpdb->prefix . 'bso_survival_score_entries';

        $timeslots = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$timeslotsTable} WHERE event_id = %d",
            $eventId
        ));

        $assignments = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$assignmentsTable} a
             INNER JOIN {$timeslotsTable} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d",
            $eventId
        ));

        $scores = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$scoresTable} se
             INNER JOIN {$assignmentsTable} a ON a.id = se.assignment_id
             INNER JOIN {$timeslotsTable} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d",
            $eventId
        ));

        return [
            'timeslots' => $timeslots,
            'assignments' => $assignments,
            'scores' => $scores,
        ];
    }

    /**
     * @return array{target_assignments: int, scores_created: int, scores_deleted: int, missing_assignments: int, obsolete_assignments: int}
     */
    public function syncScoreRecordsForEvent(int $eventId): array {
        $this->requireEditableEvent($eventId);

        $assignedParts = $this->listAssignedPartsForEvent($eventId);
        if ($assignedParts === []) {
            throw new RuntimeException('Score-record synchronisatie vereist gekoppelde onderdelen.');
        }

        $partIds = array_values(array_filter(array_map(static function ($part): int {
            return (int) ($part->id ?? 0);
        }, $assignedParts)));
        $this->ensureDefaultPartRulesForPartIds($partIds);

        $targetAssignmentIds = $this->findAssignmentIdsForEventAndParts($eventId, $partIds);
        $scoredAssignmentIds = $this->findAssignmentIdsWithScoresForEvent($eventId);

        $missingAssignmentIds = array_values(array_diff($targetAssignmentIds, $scoredAssignmentIds));
        $obsoleteAssignmentIds = array_values(array_diff($scoredAssignmentIds, $targetAssignmentIds));

        $scoresDeleted = $this->deleteScoreEntriesForAssignments($obsoleteAssignmentIds);
        $scoresCreated = $this->createInitialScoresForAssignments($missingAssignmentIds);

        return [
            'target_assignments' => count($targetAssignmentIds),
            'scores_created' => $scoresCreated,
            'scores_deleted' => $scoresDeleted,
            'missing_assignments' => count($missingAssignmentIds),
            'obsolete_assignments' => count($obsoleteAssignmentIds),
        ];
    }

    /**
     * @return array{event_id: int, linked_count: int, unlinked_count: int, linked_ids: array<int, int>, unlinked_ids: array<int, int>}
     */
    public function linkPartsToEvent(int $eventId, array $partIds): array {
        $event = $this->requireEditableEvent($eventId);

        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $partIds), static function (int $id): bool {
            return $id > 0;
        })));

        $selectedParts = $this->parts->findByIds($normalizedIds);
        if (count($selectedParts) !== count($normalizedIds)) {
            throw new InvalidArgumentException('Een of meer gekozen parts bestaan niet.');
        }

        $this->guardUniquePartNamesForEvent($selectedParts);

        foreach ($selectedParts as $part) {
            $sourceEventId = isset($part->event_id) ? (int) $part->event_id : 0;
            if ($sourceEventId <= 0 || $sourceEventId === $eventId) {
                continue;
            }

            $sourceEvent = $this->events->findById($sourceEventId);
            if ($sourceEvent === null) {
                continue;
            }

            $sourceStatus = (string) ($sourceEvent->status ?? '');
            if (!$this->isClosedLikeStatus($sourceStatus)) {
                throw new RuntimeException(sprintf('Part "%s" hangt nog aan actief event #%d.', (string) ($part->name ?? ''), $sourceEventId));
            }
        }

        $currentParts = $this->parts->findByEventId($eventId);
        $currentIds = array_values(array_unique(array_map(static function ($part): int {
            return (int) ($part->id ?? 0);
        }, $currentParts)));

        $toUnlink = array_values(array_diff($currentIds, $normalizedIds));
        $toLink = $normalizedIds;

        foreach ($toUnlink as $partId) {
            if (!$this->parts->assignToEvent($partId, null)) {
                throw new RuntimeException(sprintf('Kon part #%d niet ontkoppelen.', $partId));
            }
        }

        foreach ($toLink as $partId) {
            if (!$this->parts->assignToEvent($partId, $eventId)) {
                throw new RuntimeException(sprintf('Kon part #%d niet koppelen.', $partId));
            }
        }

        return [
            'event_id' => (int) ($event->id ?? $eventId),
            'linked_count' => count($toLink),
            'unlinked_count' => count($toUnlink),
            'linked_ids' => $toLink,
            'unlinked_ids' => $toUnlink,
        ];
    }

    /**
     * @param array<int, object> $parts
     */
    private function guardUniquePartNamesForEvent(array $parts): void {
        $seen = [];
        $duplicates = [];

        foreach ($parts as $part) {
            $name = trim((string) ($part->name ?? ''));
            if ($name === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            if (isset($seen[$key])) {
                $duplicates[$key] = $name;
                continue;
            }

            $seen[$key] = true;
        }

        if ($duplicates !== []) {
            throw new RuntimeException('Een event mag geen dubbele partnamen bevatten. Conflicterende partnaam/namen: ' . implode(', ', array_values($duplicates)) . '.');
        }
    }

    /**
     * @return array{event_id: int, status: string, detached_parts: int, summary_retained: bool}
     */
    public function deleteEventFromAdmin(int $eventId): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id moet positief zijn.');
        }

        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d niet gevonden.', $eventId));
        }

        $status = (string) ($event->status ?? '');
        $alreadyDeleted = $status === 'verwijderd';

        if (!$alreadyDeleted && $this->isClosedLikeStatus($status)) {
            $publication = $this->publications->findByEventId($eventId);
            if ($publication === null) {
                throw new RuntimeException('Gesloten events kunnen alleen verwijderd worden als er een samenvatting/publicatie bestaat.');
            }
        }

        $scoreCleanup = $this->purgeScoreDataForEvent($eventId);

        $parts = $this->parts->findByEventId($eventId);
        foreach ($parts as $part) {
            $partId = (int) ($part->id ?? 0);
            if ($partId <= 0) {
                continue;
            }

            if (!$this->parts->assignToEvent($partId, null)) {
                throw new RuntimeException(sprintf('Kon part #%d niet loskoppelen tijdens verwijderen.', $partId));
            }
        }

        if (!$alreadyDeleted && !$this->eventAdmin->markDeleted($eventId)) {
            throw new RuntimeException('Event kon niet als verwijderd gemarkeerd worden.');
        }

        return [
            'event_id' => $eventId,
            'status' => 'verwijderd',
            'already_deleted' => $alreadyDeleted,
            'detached_parts' => count($parts),
            'summary_retained' => $this->publications->findByEventId($eventId) !== null,
            'deleted_scores' => (int) ($scoreCleanup['deleted_scores'] ?? 0),
            'deleted_assignments' => (int) ($scoreCleanup['deleted_assignments'] ?? 0),
            'deleted_timeslots' => (int) ($scoreCleanup['deleted_timeslots'] ?? 0),
        ];
    }

    /**
     * @return array<int, object>
     */
    public function listLinkableParts(): array {
        return array_values(array_filter($this->parts->findAll(), static function ($part): bool {
            return (string) ($part->status ?? '') !== 'verwijderd';
        }));
    }

    /**
     * @return array<int, object>
     */
    public function listEligiblePartsForEvent(int $eventId, string $search = ''): array {
        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d niet gevonden.', $eventId));
        }

        $attachedParts = $this->parts->findByEventId($eventId);
        $attachedById = [];
        $attachedNames = [];
        foreach ($attachedParts as $part) {
            $partId = (int) ($part->id ?? 0);
            if ($partId > 0) {
                $attachedById[$partId] = true;
            }

            $name = trim((string) ($part->name ?? ''));
            if ($name !== '') {
                $attachedNames[$this->normalizePartName($name)] = true;
            }
        }

        $query = $this->normalizePartName(trim($search));
        $eligible = [];
        foreach ($this->listLinkableParts() as $part) {
            $partId = (int) ($part->id ?? 0);
            $ownerEventId = isset($part->event_id) ? (int) $part->event_id : 0;
            $isAttachedToSelected = isset($attachedById[$partId]);

            if (!$isAttachedToSelected && $ownerEventId > 0) {
                $ownerEvent = $this->events->findById($ownerEventId);
                $ownerStatus = $ownerEvent !== null ? (string) ($ownerEvent->status ?? '') : '';
                if (!$this->isClosedLikeStatus($ownerStatus) && $ownerStatus !== 'verwijderd') {
                    continue;
                }
            }

            $name = trim((string) ($part->name ?? ''));
            $nameKey = $this->normalizePartName($name);
            if (!$isAttachedToSelected && $nameKey !== '' && isset($attachedNames[$nameKey])) {
                continue;
            }

            if ($query !== '' && strpos($nameKey, $query) === false) {
                continue;
            }

            $eligible[] = $part;
        }

        usort($eligible, static function ($left, $right): int {
            $nameCompare = strcmp((string) ($left->name ?? ''), (string) ($right->name ?? ''));
            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return ((int) ($left->id ?? 0)) <=> ((int) ($right->id ?? 0));
        });

        return $eligible;
    }

    /**
     * @return array<int, object>
     */
    public function listAssignedPartsForEvent(int $eventId, string $search = ''): array {
        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d niet gevonden.', $eventId));
        }

        $query = $this->normalizePartName(trim($search));
        $assigned = [];
        foreach ($this->parts->findByEventId($eventId) as $part) {
            if ((string) ($part->status ?? '') === 'verwijderd') {
                continue;
            }

            if ($query !== '') {
                $name = $this->normalizePartName(trim((string) ($part->name ?? '')));
                if (strpos($name, $query) === false) {
                    continue;
                }
            }

            $assigned[] = $part;
        }

        usort($assigned, static function ($left, $right): int {
            $nameCompare = strcmp((string) ($left->name ?? ''), (string) ($right->name ?? ''));
            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return ((int) ($left->id ?? 0)) <=> ((int) ($right->id ?? 0));
        });

        return $assigned;
    }

    /** @return object */
    private function requireEditableEvent(int $eventId) {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id moet positief zijn.');
        }

        $event = $this->events->findById($eventId);
        if ($event === null) {
            throw new InvalidArgumentException(sprintf('Event %d niet gevonden.', $eventId));
        }

        $status = (string) ($event->status ?? '');
        if ($this->isClosedLikeStatus($status) || $status === 'verwijderd') {
            throw new RuntimeException('Gesloten of verwijderde events kunnen niet meer worden aangepast.');
        }

        return $event;
    }

    private function isClosedLikeStatus(string $status): bool {
        $normalized = function_exists('mb_strtolower') ? mb_strtolower(trim($status)) : strtolower(trim($status));
        return in_array($normalized, ['afgesloten', 'gesloten', 'closed', 'gepubliceerd'], true);
    }

    private function normalizePartName(string $name): string {
        return function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }

    private function isValidDate(string $value): bool {
        if ($value === '') {
            return false;
        }

        $parsed = date_create_from_format('Y-m-d', $value);
        if ($parsed === false) {
            return false;
        }

        return $parsed->format('Y-m-d') === $value;
    }

    private function createDemoTeamsForEvent(int $eventId, int $count): int {
        if ($eventId <= 0 || $count <= 0) {
            return 0;
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('WordPress database object is not available.');
        }

        $table = $wpdb->prefix . 'bso_survival_teams';
        $now = gmdate('Y-m-d H:i:s');
        $created = 0;

        for ($index = 1; $index <= $count; $index++) {
            $teamName = sprintf('Team%03d', $index);
            $teamNumber = str_pad((string) $index, 3, '0', STR_PAD_LEFT);

            $inserted = $wpdb->insert($table, [
                'event_id' => $eventId,
                'name' => $teamName,
                'contact_name' => 'Contact ' . $teamName,
                'contact_phone' => '06' . str_pad($teamNumber, 8, '0', STR_PAD_LEFT),
                'contact_email' => strtolower($teamName) . '@example.test',
                'status' => 'ingeschreven',
                'meta_data' => wp_json_encode(['seed' => true, 'source' => 'event_create_demo']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === false) {
                throw new RuntimeException(sprintf('Kon demo team %s niet aanmaken.', $teamName));
            }

            $created++;
        }

        return $created;
    }

    /**
     * @return array{timeslots_created: int, assignments_created: int, assignment_ids: array<int, int>}
     */
    private function generateDemoScheduleForEvent(int $eventId, string $eventDate): array {
        $teams = $this->loadTeamsForEvent($eventId);
        $parts = $this->listAssignedPartsForEvent($eventId);

        if (count($teams) < 2) {
            throw new RuntimeException('Planning + score-records genereren vereist minimaal 2 teams. Vink eerst demo teams aan of voeg teams later toe.');
        }

        if ($parts === []) {
            throw new RuntimeException('Planning + score-records genereren vereist gekoppelde onderdelen. Vink eerst alle onderdelen koppelen aan.');
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('WordPress database object is not available.');
        }

        $timeslotsTable = $wpdb->prefix . 'bso_survival_timeslots';
        $assignmentsTable = $wpdb->prefix . 'bso_survival_assignments';
        $now = gmdate('Y-m-d H:i:s');
        $eventDay = $this->isValidDate($eventDate) ? $eventDate : gmdate('Y-m-d');

        $rounds = $this->buildRoundRobinPairs($teams, count($parts));
        $timeslotsCreated = 0;
        $assignmentsCreated = 0;
        $assignmentIds = [];
        $slotIndex = 0;
        $partCount = count($parts);

        foreach ($rounds as $roundIndex => $pairs) {
            $batches = array_chunk($pairs, $partCount);
            foreach ($batches as $batchIndex => $batchPairs) {
                $window = $this->buildTimeslotWindow($eventDay, $slotIndex);
                $inserted = $wpdb->insert($timeslotsTable, [
                    'event_id' => $eventId,
                    'start_at' => $window['start_at'],
                    'end_at' => $window['end_at'],
                    'transfer_minutes' => 5,
                    'status' => 'planned',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($inserted === false) {
                    throw new RuntimeException('Kon geen timeslot genereren voor demo event.');
                }

                $timeslotId = (int) $wpdb->insert_id;
                $timeslotsCreated++;

                foreach ($batchPairs as $pairOffset => $pair) {
                    $part = $parts[($roundIndex + $batchIndex + $pairOffset) % $partCount] ?? null;
                    $partId = is_object($part) ? (int) ($part->id ?? 0) : 0;
                    if ($partId <= 0) {
                        continue;
                    }

                    foreach ($pair as $team) {
                        $teamId = is_object($team) ? (int) ($team->id ?? 0) : 0;
                        if ($teamId <= 0) {
                            continue;
                        }

                        $assignmentInserted = $wpdb->insert($assignmentsTable, [
                            'timeslot_id' => $timeslotId,
                            'part_id' => $partId,
                            'team_id' => $teamId,
                            'referee_primary_id' => null,
                            'referee_secondary_id' => null,
                            'source' => 'planner_demo',
                            'status' => 'planned',
                            'meta_data' => wp_json_encode([
                                'seed' => true,
                                'source' => 'event_create_demo',
                                'round' => $roundIndex + 1,
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        if ($assignmentInserted === false) {
                            throw new RuntimeException(sprintf('Kon assignment niet genereren voor team #%d.', $teamId));
                        }

                        $assignmentId = (int) $wpdb->insert_id;
                        if ($assignmentId > 0) {
                            $assignmentIds[] = $assignmentId;
                        }
                        $assignmentsCreated++;
                    }
                }

                $slotIndex++;
            }
        }

        return [
            'timeslots_created' => $timeslotsCreated,
            'assignments_created' => $assignmentsCreated,
            'assignment_ids' => $assignmentIds,
        ];
    }

    /**
     * @param array<int, int> $assignmentIds
     */
    private function createInitialScoresForAssignments(array $assignmentIds): int {
        $assignmentIds = array_values(array_filter(array_map('intval', $assignmentIds), static function (int $id): bool {
            return $id > 0;
        }));

        if ($assignmentIds === []) {
            return 0;
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('WordPress database object is not available.');
        }

        $table = $wpdb->prefix . 'bso_survival_score_entries';
        $now = gmdate('Y-m-d H:i:s');
        $created = 0;

        foreach ($assignmentIds as $assignmentId) {
            $inserted = $wpdb->insert($table, [
                'assignment_id' => $assignmentId,
                'raw_value' => 0,
                'bonus_points' => 0,
                'normalized_points' => 0,
                'position' => null,
                'rank_points' => null,
                'joker_applied' => 0,
                'entered_by_role' => 'admin_init',
                'entered_at' => $now,
                'status' => 'concept',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === false) {
                throw new RuntimeException(sprintf('Kon score-record voor assignment #%d niet genereren.', $assignmentId));
            }

            $created++;
        }

        return $created;
    }

    /**
     * @param array<int, int> $partIds
     * @return array<int, int>
     */
    private function findAssignmentIdsForEventAndParts(int $eventId, array $partIds): array {
        if ($eventId <= 0 || $partIds === []) {
            return [];
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('WordPress database object is not available.');
        }

        $timeslotsTable = $wpdb->prefix . 'bso_survival_timeslots';
        $assignmentsTable = $wpdb->prefix . 'bso_survival_assignments';
        $placeholders = implode(',', array_fill(0, count($partIds), '%d'));
        $args = array_merge([$eventId], $partIds);

        $sql = $wpdb->prepare(
            "SELECT a.id
             FROM {$assignmentsTable} a
             INNER JOIN {$timeslotsTable} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d
               AND a.part_id IN ({$placeholders})
             ORDER BY a.id ASC",
            $args
        );

        $rows = $wpdb->get_col($sql) ?: [];
        return array_values(array_unique(array_map('intval', $rows)));
    }

    /**
     * @return array<int, int>
     */
    private function findAssignmentIdsWithScoresForEvent(int $eventId): array {
        if ($eventId <= 0) {
            return [];
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('WordPress database object is not available.');
        }

        $timeslotsTable = $wpdb->prefix . 'bso_survival_timeslots';
        $assignmentsTable = $wpdb->prefix . 'bso_survival_assignments';
        $scoresTable = $wpdb->prefix . 'bso_survival_score_entries';

        $sql = $wpdb->prepare(
            "SELECT DISTINCT a.id
             FROM {$scoresTable} se
             INNER JOIN {$assignmentsTable} a ON a.id = se.assignment_id
             INNER JOIN {$timeslotsTable} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d
             ORDER BY a.id ASC",
            $eventId
        );

        $rows = $wpdb->get_col($sql) ?: [];
        return array_values(array_unique(array_map('intval', $rows)));
    }

    /**
     * @param array<int, int> $assignmentIds
     */
    private function deleteScoreEntriesForAssignments(array $assignmentIds): int {
        $assignmentIds = array_values(array_filter(array_map('intval', $assignmentIds), static function (int $id): bool {
            return $id > 0;
        }));

        if ($assignmentIds === []) {
            return 0;
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('WordPress database object is not available.');
        }

        $scoresTable = $wpdb->prefix . 'bso_survival_score_entries';
        $placeholders = implode(',', array_fill(0, count($assignmentIds), '%d'));
        $sql = $wpdb->prepare(
            "DELETE FROM {$scoresTable} WHERE assignment_id IN ({$placeholders})",
            $assignmentIds
        );

        $deleted = $wpdb->query($sql);
        if ($deleted === false) {
            throw new RuntimeException('Overbodige score-records konden niet worden verwijderd.');
        }

        return (int) $deleted;
    }

    /**
     * @return array<int, object>
     */
    private function loadTeamsForEvent(int $eventId): array {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('WordPress database object is not available.');
        }

        $table = $wpdb->prefix . 'bso_survival_teams';
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d AND status <> %s ORDER BY id ASC",
            $eventId,
            'verwijderd'
        );

        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * @param array<int, object> $teams
     * @return array<int, array<int, array<int, object>>>
     */
    private function buildRoundRobinPairs(array $teams, int $requestedRounds): array {
        $pool = array_values($teams);
        if (count($pool) % 2 !== 0) {
            $pool[] = null;
        }

        $teamCount = count($pool);
        $maxRounds = max(1, $teamCount - 1);
        $roundsToGenerate = max(1, min($requestedRounds, $maxRounds));
        $rounds = [];

        for ($round = 0; $round < $roundsToGenerate; $round++) {
            $pairs = [];
            $half = (int) ($teamCount / 2);
            for ($index = 0; $index < $half; $index++) {
                $left = $pool[$index] ?? null;
                $right = $pool[$teamCount - 1 - $index] ?? null;
                if (!is_object($left) || !is_object($right)) {
                    continue;
                }

                $pairs[] = [$left, $right];
            }

            $rounds[] = $pairs;

            $first = array_shift($pool);
            $last = array_pop($pool);
            array_unshift($pool, $first);
            array_splice($pool, 1, 0, [$last]);
        }

        return $rounds;
    }

    /**
     * @return array{start_at: string, end_at: string}
     */
    private function buildTimeslotWindow(string $eventDate, int $slotIndex): array {
        $base = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $eventDate . ' 09:00:00', new \DateTimeZone('UTC'));
        if ($base === false) {
            $base = new \DateTimeImmutable(gmdate('Y-m-d') . ' 09:00:00', new \DateTimeZone('UTC'));
        }

        $start = $base->modify('+' . ($slotIndex * 35) . ' minutes');
        $end = $start->modify('+30 minutes');

        return [
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $end->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{deleted_scores: int, deleted_assignments: int, deleted_timeslots: int}
     */
    private function purgeScoreDataForEvent(int $eventId): array {
        if ($eventId <= 0) {
            return [
                'deleted_scores' => 0,
                'deleted_assignments' => 0,
                'deleted_timeslots' => 0,
            ];
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return [
                'deleted_scores' => 0,
                'deleted_assignments' => 0,
                'deleted_timeslots' => 0,
            ];
        }

        $timeslotsTable = $wpdb->prefix . 'bso_survival_timeslots';
        $assignmentsTable = $wpdb->prefix . 'bso_survival_assignments';
        $scoresTable = $wpdb->prefix . 'bso_survival_score_entries';
        $jokerTable = $wpdb->prefix . 'bso_survival_joker_usages';

        $scoreCountSql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$scoresTable} se
             INNER JOIN {$assignmentsTable} a ON a.id = se.assignment_id
             INNER JOIN {$timeslotsTable} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d",
            $eventId
        );
        $assignmentCountSql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$assignmentsTable} a
             INNER JOIN {$timeslotsTable} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d",
            $eventId
        );
        $timeslotCountSql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$timeslotsTable} WHERE event_id = %d",
            $eventId
        );

        $deletedScores = (int) $wpdb->get_var($scoreCountSql);
        $deletedAssignments = (int) $wpdb->get_var($assignmentCountSql);
        $deletedTimeslots = (int) $wpdb->get_var($timeslotCountSql);

        $deleteJokersSql = $wpdb->prepare(
            "DELETE FROM {$jokerTable} WHERE event_id = %d",
            $eventId
        );
        $wpdb->query($deleteJokersSql);

        $deleteScoresSql = $wpdb->prepare(
            "DELETE se
             FROM {$scoresTable} se
             INNER JOIN {$assignmentsTable} a ON a.id = se.assignment_id
             INNER JOIN {$timeslotsTable} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d",
            $eventId
        );
        $wpdb->query($deleteScoresSql);

        $deleteAssignmentsSql = $wpdb->prepare(
            "DELETE a
             FROM {$assignmentsTable} a
             INNER JOIN {$timeslotsTable} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d",
            $eventId
        );
        $wpdb->query($deleteAssignmentsSql);

        $deleteTimeslotsSql = $wpdb->prepare(
            "DELETE FROM {$timeslotsTable} WHERE event_id = %d",
            $eventId
        );
        $wpdb->query($deleteTimeslotsSql);

        return [
            'deleted_scores' => $deletedScores,
            'deleted_assignments' => $deletedAssignments,
            'deleted_timeslots' => $deletedTimeslots,
        ];
    }

    /**
     * @param array<int, int> $partIds
     */
    private function ensureDefaultPartRulesForPartIds(array $partIds): int {
        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $partIds), static function (int $id): bool {
            return $id > 0;
        })));

        if ($normalizedIds === []) {
            return 0;
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('WordPress database object is not available.');
        }

        $table = $wpdb->prefix . 'bso_survival_part_rules';
        $placeholders = implode(',', array_fill(0, count($normalizedIds), '%d'));
        $sql = $wpdb->prepare(
            "SELECT part_id FROM {$table} WHERE part_id IN ({$placeholders})",
            ...$normalizedIds
        );
        $existingRows = $wpdb->get_col($sql) ?: [];
        $existingLookup = array_fill_keys(array_map('intval', $existingRows), true);

        $configJson = function_exists('wp_json_encode')
            ? wp_json_encode([
                'normalization_curve' => 'linear',
                'max_points' => 100,
            ])
            : json_encode([
                'normalization_curve' => 'linear',
                'max_points' => 100,
            ]);
        if (!is_string($configJson)) {
            throw new RuntimeException('Default scoring_config kon niet worden opgebouwd.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $created = 0;

        foreach ($normalizedIds as $partId) {
            if (isset($existingLookup[$partId])) {
                continue;
            }

            $inserted = $wpdb->insert($table, [
                'part_id' => $partId,
                'scoring_mode' => 'points',
                'unit' => 'points',
                'tiebreaker_mode' => 'manual_referee',
                'scoring_config' => $configJson,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === false) {
                throw new RuntimeException(sprintf('Kon standaard scoreregel voor onderdeel #%d niet aanmaken.', $partId));
            }

            $created++;
        }

        return $created;
    }
}
