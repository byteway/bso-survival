<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use BSO\Survival\Database\Repository\ScoreEntryRepositoryInterface;
use BSO\Survival\Support\Capabilities;
use InvalidArgumentException;
use RuntimeException;

class AdminScoreService {
    /** @var DashboardOverviewService */
    private $overview;

    /** @var AssignmentRepositoryInterface */
    private $assignments;

    /** @var ScoreEntryRepositoryInterface */
    private $entries;

    /** @var ScoreEntryService */
    private $scoreEntryService;

    /** @var RankingService */
    private $ranking;

    /** @var AuditLogService */
    private $audit;

    /** @var PartConfirmationService|null */
    private $partConfirmations;

    public function __construct(
        DashboardOverviewService $overview,
        AssignmentRepositoryInterface $assignments,
        ScoreEntryRepositoryInterface $entries,
        ScoreEntryService $scoreEntryService,
        RankingService $ranking,
        AuditLogService $audit,
        PartConfirmationService $partConfirmations = null
    ) {
        $this->overview = $overview;
        $this->assignments = $assignments;
        $this->entries = $entries;
        $this->scoreEntryService = $scoreEntryService;
        $this->ranking = $ranking;
        $this->audit = $audit;
        $this->partConfirmations = $partConfirmations;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array {
        $eventId = (int) ($payload['event_id'] ?? 0);
        $assignmentId = (int) ($payload['assignment_id'] ?? 0);
        $rawValue = $payload['raw_value'] ?? null;
        $bonusPoints = $payload['bonus_points'] ?? 0;
        $changedBy = trim((string) ($payload['changed_by'] ?? 'admin'));
        $enteredByRole = trim((string) ($payload['entered_by_role'] ?? 'admin'));
        $jokerApplied = $this->toBool($payload['joker_applied'] ?? false);
        $jokerValidatedBy = trim((string) ($payload['joker_validated_by'] ?? $changedBy));
        $meta = $this->normalizeMeta($payload['meta'] ?? null);

        $assignment = $this->validateWritableAssignment($eventId, $assignmentId, $rawValue);
        $partId = (int) ($assignment->part_id ?? 0);
        $teamId = (int) ($assignment->team_id ?? 0);

        $this->validateAdditionalScorePolicy($eventId, $assignmentId, $partId, $teamId);

        $stored = $this->scoreEntryService->submit($partId, $assignmentId, $rawValue, $bonusPoints, $enteredByRole, [
            'source' => (string) ($meta['source'] ?? 'admin_score'),
            'event_id' => $eventId,
            'labels' => $meta['labels'] ?? [],
            'trace_id' => (string) ($meta['trace_id'] ?? ''),
        ]);

        $stored = $this->applyJokerState(
            $eventId,
            $teamId,
            (int) ($stored->id ?? 0),
            $jokerApplied,
            $jokerValidatedBy !== '' ? $jokerValidatedBy : ($changedBy !== '' ? $changedBy : 'admin'),
            (float) ($stored->normalized_points ?? 0)
        );

        $teamNormalizedPoints = $this->entries->findLatestNormalizedPointsByPart($eventId, $partId);
        $positions = $this->ranking->refreshForPartWithNormalized($partId, $teamNormalizedPoints);

        $this->audit->log(
            $eventId,
            'score_entry',
            (int) ($stored->id ?? 0),
            'created',
            null,
            [
                'assignment_id' => $assignmentId,
                'raw_value' => (float) $rawValue,
                'bonus_points' => (float) $bonusPoints,
                'normalized_points' => (float) ($stored->normalized_points ?? 0),
                'joker_applied' => (int) ($stored->joker_applied ?? 0),
                'meta' => $meta,
            ],
            $changedBy
        );

        return [
            'score_entry_id' => (int) ($stored->id ?? 0),
            'assignment_id' => $assignmentId,
            'event_id' => $eventId,
            'part_id' => $partId,
            'bonus_points' => (float) ($stored->bonus_points ?? 0),
            'normalized_points' => (float) ($stored->normalized_points ?? 0),
            'joker_applied' => (int) ($stored->joker_applied ?? 0),
            'positions' => $positions,
            'auto_created_part_rule_ids' => $this->scoreEntryService->consumeAutoCreatedPartIds(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(int $scoreEntryId, array $payload): array {
        if ($scoreEntryId <= 0) {
            throw new InvalidArgumentException('score_entry_id must be a positive integer.');
        }

        $eventId = (int) ($payload['event_id'] ?? 0);
        $rawValue = $payload['raw_value'] ?? null;
        $bonusPoints = $payload['bonus_points'] ?? 0;
        $changedBy = trim((string) ($payload['changed_by'] ?? 'admin'));
        $enteredByRole = trim((string) ($payload['entered_by_role'] ?? 'admin'));
        $jokerApplied = $this->toBool($payload['joker_applied'] ?? false);
        $jokerValidatedBy = trim((string) ($payload['joker_validated_by'] ?? $changedBy));
        $meta = $this->normalizeMeta($payload['meta'] ?? null);

        $existing = $this->entries->findById($scoreEntryId);
        if ($existing === null) {
            throw new InvalidArgumentException(sprintf('score entry %d not found.', $scoreEntryId));
        }

        $existingAssignmentId = (int) ($existing->assignment_id ?? 0);
        $requestedAssignmentId = (int) ($payload['assignment_id'] ?? 0);
        $assignmentId = $requestedAssignmentId > 0 ? $requestedAssignmentId : $existingAssignmentId;

        $existingAssignment = $this->validateWritableAssignment($eventId, $existingAssignmentId, $rawValue);
        $assignment = $assignmentId === $existingAssignmentId
            ? $existingAssignment
            : $this->validateWritableAssignment($eventId, $assignmentId, $rawValue);

        if (
            (int) ($assignment->part_id ?? 0) !== (int) ($existingAssignment->part_id ?? 0)
            || (int) ($assignment->team_id ?? 0) !== (int) ($existingAssignment->team_id ?? 0)
        ) {
            throw new InvalidArgumentException('Alleen tijdslotwissel binnen hetzelfde team en onderdeel is toegestaan.');
        }

        if ($assignmentId !== $existingAssignmentId && $this->assignmentHasOtherScoreEntry($assignmentId, $scoreEntryId)) {
            throw new RuntimeException('Het gekozen tijdslot heeft al een score-entry voor dit assignment. Kies een ander tijdslot.');
        }

        $partId = (int) ($assignment->part_id ?? 0);
        $teamId = (int) ($assignment->team_id ?? 0);

        $updated = $this->scoreEntryService->updateEntry(
            $scoreEntryId,
            $partId,
            $assignmentId,
            $rawValue,
            $bonusPoints,
            $enteredByRole,
            [
                'source' => (string) ($meta['source'] ?? 'admin_score_edit'),
                'event_id' => $eventId,
                'labels' => $meta['labels'] ?? [],
                'trace_id' => (string) ($meta['trace_id'] ?? ''),
            ]
        );

        $updated = $this->applyJokerState(
            $eventId,
            $teamId,
            $scoreEntryId,
            $jokerApplied,
            $jokerValidatedBy !== '' ? $jokerValidatedBy : ($changedBy !== '' ? $changedBy : 'admin'),
            (float) ($updated->normalized_points ?? 0)
        );

        $teamNormalizedPoints = $this->entries->findLatestNormalizedPointsByPart($eventId, $partId);
        $positions = $this->ranking->refreshForPartWithNormalized($partId, $teamNormalizedPoints);

        $this->audit->log(
            $eventId,
            'score_entry',
            $scoreEntryId,
            'updated',
            [
                'assignment_id' => (int) ($existing->assignment_id ?? 0),
                'raw_value' => (float) ($existing->raw_value ?? 0),
                'bonus_points' => (float) ($existing->bonus_points ?? 0),
                'normalized_points' => (float) ($existing->normalized_points ?? 0),
                'joker_applied' => (int) ($existing->joker_applied ?? 0),
            ],
            [
                'assignment_id' => $assignmentId,
                'raw_value' => (float) $rawValue,
                'bonus_points' => (float) $bonusPoints,
                'normalized_points' => (float) ($updated->normalized_points ?? 0),
                'joker_applied' => (int) ($updated->joker_applied ?? 0),
                'meta' => $meta,
            ],
            $changedBy
        );

        return [
            'score_entry_id' => $scoreEntryId,
            'assignment_id' => $assignmentId,
            'event_id' => $eventId,
            'part_id' => $partId,
            'bonus_points' => (float) ($updated->bonus_points ?? 0),
            'normalized_points' => (float) ($updated->normalized_points ?? 0),
            'joker_applied' => (int) ($updated->joker_applied ?? 0),
            'positions' => $positions,
            'auto_created_part_rule_ids' => $this->scoreEntryService->consumeAutoCreatedPartIds(),
        ];
    }

    private function assignmentHasOtherScoreEntry(int $assignmentId, int $excludeScoreEntryId): bool {
        global $wpdb;
        if (!is_object($wpdb) || $assignmentId <= 0) {
            return false;
        }

        $scoreEntries = $wpdb->prefix . 'bso_survival_score_entries';
        $sql = $wpdb->prepare(
            "SELECT id, entered_by_role FROM {$scoreEntries} WHERE assignment_id = %d AND id != %d",
            $assignmentId,
            max(0, $excludeScoreEntryId)
        );

        $rows = $wpdb->get_results($sql) ?: [];
        foreach ($rows as $row) {
            $role = (string) ($row->entered_by_role ?? '');
            if ($role !== 'admin_init') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function recalculate(array $payload): array {
        $eventId = (int) ($payload['event_id'] ?? 0);
        $partId = (int) ($payload['part_id'] ?? 0);
        $changedBy = trim((string) ($payload['changed_by'] ?? 'admin'));

        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($partId <= 0) {
            throw new InvalidArgumentException('part_id must be a positive integer.');
        }

        $teamNormalizedPoints = $this->entries->findLatestNormalizedPointsByPart($eventId, $partId);
        $positions = $this->ranking->refreshForPartWithNormalized($partId, $teamNormalizedPoints);

        $this->audit->log(
            $eventId,
            'ranking',
            $partId,
            'recalculated',
            null,
            [
                'team_count' => count($teamNormalizedPoints),
            ],
            $changedBy === '' ? 'admin' : $changedBy
        );

        return [
            'event_id' => $eventId,
            'part_id' => $partId,
            'team_count' => count($teamNormalizedPoints),
            'positions' => $positions,
        ];
    }

    private function toBool($value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim((string) $value))
            : strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function validateAdditionalScorePolicy(int $eventId, int $assignmentId, int $partId, int $teamId): void {
        if ($eventId <= 0 || $assignmentId <= 0 || $partId <= 0 || $teamId <= 0) {
            throw new InvalidArgumentException('score policy context is invalid.');
        }

        if ($this->assignmentHasScoreEntry($assignmentId)) {
            throw new RuntimeException('Niet toegestaan: dit assignment heeft al een score. Gebruik Bewerken om de score aan te passen. Actie geannuleerd.');
        }

        $existingAssignments = $this->findScoredAssignmentIdsForTeamPart($eventId, $partId, $teamId);
        if ($existingAssignments === []) {
            return;
        }

        if (!in_array($assignmentId, $existingAssignments, true) && !$this->hasEqualAssignmentCountsForAllTeams($eventId, $partId)) {
            throw new RuntimeException('Niet toegestaan: extra score op hetzelfde onderdeel is alleen toegestaan als het totaal aantal scores van alle teams gelijk blijft. Actie geannuleerd.');
        }
    }

    private function assignmentHasScoreEntry(int $assignmentId): bool {
        global $wpdb;
        if (!is_object($wpdb) || $assignmentId <= 0) {
            return false;
        }

        $scoreEntries = $wpdb->prefix . 'bso_survival_score_entries';
        $sql = $wpdb->prepare(
            "SELECT id FROM {$scoreEntries} WHERE assignment_id = %d LIMIT 1",
            $assignmentId
        );

        $row = $wpdb->get_row($sql);

        return is_object($row);
    }

    /**
     * @return array<int, int>
     */
    private function findScoredAssignmentIdsForTeamPart(int $eventId, int $partId, int $teamId): array {
        global $wpdb;
        if (!is_object($wpdb) || $eventId <= 0 || $partId <= 0 || $teamId <= 0) {
            return [];
        }

        $scoreEntries = $wpdb->prefix . 'bso_survival_score_entries';
        $assignments = $wpdb->prefix . 'bso_survival_assignments';
        $timeslots = $wpdb->prefix . 'bso_survival_timeslots';

        $sql = $wpdb->prepare(
            "SELECT DISTINCT se.assignment_id
             FROM {$scoreEntries} se
             INNER JOIN {$assignments} a ON a.id = se.assignment_id
             INNER JOIN {$timeslots} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d
               AND a.part_id = %d
               AND a.team_id = %d",
            $eventId,
            $partId,
            $teamId
        );

        $rows = $wpdb->get_col($sql) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $id = (int) $row;
            if ($id > 0) {
                $result[] = $id;
            }
        }

        return $result;
    }

    private function hasEqualAssignmentCountsForAllTeams(int $eventId, int $partId): bool {
        global $wpdb;
        if (!is_object($wpdb) || $eventId <= 0 || $partId <= 0) {
            return false;
        }

        $assignments = $wpdb->prefix . 'bso_survival_assignments';
        $timeslots = $wpdb->prefix . 'bso_survival_timeslots';

        $sql = $wpdb->prepare(
            "SELECT a.team_id, COUNT(*) AS assignment_count
             FROM {$assignments} a
             INNER JOIN {$timeslots} ts ON ts.id = a.timeslot_id
             WHERE ts.event_id = %d
               AND a.part_id = %d
             GROUP BY a.team_id",
            $eventId,
            $partId
        );

        $rows = $wpdb->get_results($sql) ?: [];
        if ($rows === []) {
            return false;
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[] = (int) ($row->assignment_count ?? 0);
        }

        return count(array_unique($counts)) === 1;
    }

    /**
     * @return object
     */
    private function applyJokerState(
        int $eventId,
        int $teamId,
        int $scoreEntryId,
        bool $jokerApplied,
        string $validatedBy,
        float $baseNormalizedPoints
    ) {
        if ($eventId <= 0 || $teamId <= 0 || $scoreEntryId <= 0) {
            throw new InvalidArgumentException('joker state context is invalid.');
        }

        global $wpdb;
        if (!is_object($wpdb)) {
            throw new RuntimeException('Database verbinding niet beschikbaar voor jokerregistratie.');
        }

        $jokerTable = $wpdb->prefix . 'bso_survival_joker_usages';
        $now = gmdate('Y-m-d H:i:s');
        $usage = $this->findJokerUsage($eventId, $teamId);

        if ($jokerApplied) {
            if ($usage !== null && (int) ($usage->score_entry_id ?? 0) !== $scoreEntryId) {
                throw new RuntimeException('Joker is al ingezet voor dit team in dit event.');
            }

            $entry = $this->entries->updateById($scoreEntryId, [
                'joker_applied' => 1,
                'normalized_points' => $baseNormalizedPoints * 2,
                'updated_at' => $now,
            ]);

            if ($entry === null) {
                throw new RuntimeException('Joker kon niet op de score worden toegepast.');
            }

            $record = [
                'event_id' => $eventId,
                'team_id' => $teamId,
                'score_entry_id' => $scoreEntryId,
                'used_at' => $now,
                'validated_by' => $validatedBy !== '' ? $validatedBy : 'admin',
                'updated_at' => $now,
            ];

            if ($usage === null) {
                $record['created_at'] = $now;
                $inserted = $wpdb->insert($jokerTable, $record);
                if ($inserted === false) {
                    throw new RuntimeException('Jokerregistratie kon niet worden opgeslagen.');
                }
            } else {
                $updated = $wpdb->update($jokerTable, $record, ['id' => (int) $usage->id]);
                if ($updated === false) {
                    throw new RuntimeException('Jokerregistratie kon niet worden bijgewerkt.');
                }
            }

            return $entry;
        }

        $entry = $this->entries->updateById($scoreEntryId, [
            'joker_applied' => 0,
            'normalized_points' => $baseNormalizedPoints,
            'updated_at' => $now,
        ]);

        if ($entry === null) {
            throw new RuntimeException('Score kon niet worden bijgewerkt zonder joker.');
        }

        if ($usage !== null && (int) ($usage->score_entry_id ?? 0) === $scoreEntryId) {
            $deleted = $wpdb->delete($jokerTable, ['id' => (int) $usage->id]);
            if ($deleted === false) {
                throw new RuntimeException('Jokerregistratie kon niet worden verwijderd.');
            }
        }

        return $entry;
    }

    /**
     * @return object|null
     */
    private function findJokerUsage(int $eventId, int $teamId) {
        global $wpdb;
        if (!is_object($wpdb) || $eventId <= 0 || $teamId <= 0) {
            return null;
        }

        $jokerTable = $wpdb->prefix . 'bso_survival_joker_usages';
        $sql = $wpdb->prepare(
            "SELECT * FROM {$jokerTable} WHERE event_id = %d AND team_id = %d LIMIT 1",
            $eventId,
            $teamId
        );

        return $wpdb->get_row($sql) ?: null;
    }

    /**
     * Maak ontbrekende score-records aan voor alle assignments van een event.
     * De onderliggende assignment-planning blijft leidend voor bestaande constraints.
     *
     * @return array<string, int>
     */
    public function initializeForEvent(int $eventId, string $changedBy = 'admin'): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        $overview = $this->overview->getOverviewForEvent($eventId);
        if (!empty($overview['status']['is_read_only']) || !empty($overview['status']['is_published'])) {
            throw new RuntimeException('Score-invoer is geblokkeerd omdat event read-only of gepubliceerd is.');
        }

        $assignments = $this->assignments->findByEventId($eventId);
        $assignmentIds = [];
        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment->id ?? 0);
            if ($assignmentId > 0) {
                $assignmentIds[] = $assignmentId;
            }
        }

        if ($assignmentIds === []) {
            throw new RuntimeException('Geen assignments gevonden voor dit event. Initialiseer eerst eventplanning.');
        }

        $existingAssignmentIds = $this->entries->findAssignmentIdsWithEntries($assignmentIds);
        $existingLookup = array_fill_keys($existingAssignmentIds, true);

        $created = 0;
        $skipped = 0;
        $now = gmdate('Y-m-d H:i:s');

        foreach ($assignmentIds as $assignmentId) {
            if (isset($existingLookup[$assignmentId])) {
                $skipped++;
                continue;
            }

            $stored = $this->entries->insert([
                'assignment_id' => $assignmentId,
                'raw_value' => 0,
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

            if ($stored === null) {
                throw new RuntimeException(sprintf('Initialisatie mislukt voor assignment #%d.', $assignmentId));
            }

            $created++;
        }

        $this->audit->log(
            $eventId,
            'event',
            $eventId,
            'initialized',
            null,
            [
                'assignment_count' => count($assignmentIds),
                'created_entries' => $created,
                'skipped_existing' => $skipped,
            ],
            trim($changedBy) !== '' ? trim($changedBy) : 'admin'
        );

        return [
            'assignment_count' => count($assignmentIds),
            'created_entries' => $created,
            'skipped_existing' => $skipped,
        ];
    }

    /**
     * @param mixed $rawValue
     * @return object
     */
    private function validateWritableAssignment(int $eventId, int $assignmentId, $rawValue) {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($assignmentId <= 0) {
            throw new InvalidArgumentException('assignment_id must be a positive integer.');
        }

        if (!is_numeric($rawValue)) {
            throw new InvalidArgumentException('raw_value must be numeric.');
        }

        $overview = $this->overview->getOverviewForEvent($eventId);
        if (!empty($overview['status']['is_read_only']) || !empty($overview['status']['is_published'])) {
            throw new RuntimeException('Score-invoer is geblokkeerd omdat event read-only of gepubliceerd is.');
        }

        $assignment = $this->assignments->findById($assignmentId);
        if ($assignment === null) {
            throw new InvalidArgumentException(sprintf('assignment %d not found.', $assignmentId));
        }

        if ((int) ($assignment->event_id ?? 0) !== $eventId) {
            throw new InvalidArgumentException('assignment_id hoort niet bij dit event_id.');
        }

        if ((int) ($assignment->part_id ?? 0) <= 0 || (int) ($assignment->team_id ?? 0) <= 0) {
            throw new RuntimeException('assignment bevat ongeldige part/team koppeling.');
        }

        $partId = (int) ($assignment->part_id ?? 0);
        if ($this->partConfirmations !== null && $this->partConfirmations->isPartConfirmed($eventId, $partId) && !Capabilities::canManageSettings()) {
            throw new RuntimeException('Score-invoer is geblokkeerd: dit onderdeel is bevestigd door de scheidsrechter. Alleen de leiding kan nog wijzigingen doen.');
        }

        return $assignment;
    }

    /**
     * @param mixed $meta
     * @return array<string, mixed>
     */
    private function normalizeMeta($meta): array {
        if ($meta === null) {
            return [];
        }

        if (!is_array($meta)) {
            throw new InvalidArgumentException('meta moet een object zijn.');
        }

        $allowedKeys = ['source', 'labels', 'trace_id'];
        $unknown = array_diff(array_keys($meta), $allowedKeys);
        if ($unknown !== []) {
            throw new InvalidArgumentException('meta bevat onbekende velden: ' . implode(',', $unknown));
        }

        $normalized = [];

        if (array_key_exists('source', $meta)) {
            $source = trim((string) $meta['source']);
            if ($source === '') {
                throw new InvalidArgumentException('meta.source moet een niet-lege string zijn.');
            }

            $normalized['source'] = $source;
        }

        if (array_key_exists('trace_id', $meta)) {
            $traceId = trim((string) $meta['trace_id']);
            if ($traceId === '') {
                throw new InvalidArgumentException('meta.trace_id moet een niet-lege string zijn.');
            }

            $normalized['trace_id'] = $traceId;
        }

        if (array_key_exists('labels', $meta)) {
            if (!is_array($meta['labels'])) {
                throw new InvalidArgumentException('meta.labels moet een array van strings zijn.');
            }

            $labels = [];
            foreach ($meta['labels'] as $label) {
                $value = trim((string) $label);
                if ($value === '') {
                    throw new InvalidArgumentException('meta.labels mag geen lege waarden bevatten.');
                }

                $labels[] = $value;
            }

            $normalized['labels'] = array_values(array_unique($labels));
        }

        return $normalized;
    }
}
