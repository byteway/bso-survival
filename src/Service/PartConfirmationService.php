<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class PartConfirmationService {
    private const OPTION_KEY = 'bso_survival_part_confirmations';

    /** @var array<string, mixed> */
    private static $memoryState = [];

    /** @var InterimTeamScoreService */
    private $interim;

    /** @var AssignmentRepositoryInterface */
    private $assignments;

    /** @var TeamService|null */
    private $teams;

    /** @var EventPublicationService|null */
    private $publications;

    /** @var PublicationNotificationService|null */
    private $notifications;

    public function __construct(
        InterimTeamScoreService $interim,
        AssignmentRepositoryInterface $assignments,
        TeamService $teams = null,
        EventPublicationService $publications = null,
        PublicationNotificationService $notifications = null
    ) {
        $this->interim = $interim;
        $this->assignments = $assignments;
        $this->teams = $teams;
        $this->publications = $publications;
        $this->notifications = $notifications;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPartStatus(int $eventId, int $partId): array {
        $this->guardPositive($eventId, 'event_id');
        $this->guardPositive($partId, 'part_id');

        $overview = $this->interim->getPartOverview($eventId, $partId);
        $rows = is_array($overview['rows'] ?? null) ? $overview['rows'] : [];
        $counts = is_array($overview['counts'] ?? null) ? $overview['counts'] : [];

        $ties = $this->findTies($rows);
        $confirmed = $this->isPartConfirmed($eventId, $partId);
        $completed = (int) ($counts['completed'] ?? 0);
        $pending = (int) ($counts['pending'] ?? 0);
        $total = (int) ($counts['total'] ?? 0);

        return [
            'event_id' => $eventId,
            'part_id' => $partId,
            'confirmed' => $confirmed,
            'completed_count' => $completed,
            'pending_count' => $pending,
            'total_count' => $total,
            'part_complete' => $total > 0 && $pending === 0,
            'has_ties' => $ties !== [],
            'tie_groups' => $ties,
            'can_confirm' => !$confirmed && $total > 0 && $pending === 0 && $ties === [],
        ];
    }

    public function isPartConfirmed(int $eventId, int $partId): bool {
        $this->guardPositive($eventId, 'event_id');
        $this->guardPositive($partId, 'part_id');

        $state = $this->readState();
        return isset($state[(string) $eventId]['parts'][(string) $partId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmPart(int $eventId, int $partId, string $changedBy, bool $confirmNoChanges): array {
        $this->guardPositive($eventId, 'event_id');
        $this->guardPositive($partId, 'part_id');

        $changedBy = trim($changedBy);
        if ($changedBy === '') {
            $changedBy = 'scheidsrechter';
        }

        if (!$confirmNoChanges) {
            throw new RuntimeException('Bevestiging vereist expliciete akkoordverklaring dat geen scorewijzigingen meer nodig zijn.');
        }

        $status = $this->getPartStatus($eventId, $partId);
        if (!empty($status['confirmed'])) {
            return [
                'confirmed' => true,
                'already_confirmed' => true,
                'status' => $status,
                'finalization' => $this->readFinalizationState($eventId),
            ];
        }

        if (empty($status['part_complete'])) {
            throw new RuntimeException('Onderdeel kan nog niet bevestigd worden: niet alle teams hebben een afgeronde score.');
        }

        if (!empty($status['has_ties'])) {
            throw new RuntimeException('Onderdeel kan niet bevestigd worden: er bestaan nog gelijke scores (ties). Los deze eerst op.');
        }

        $state = $this->readState();
        $eventKey = (string) $eventId;
        $partKey = (string) $partId;
        if (!isset($state[$eventKey]) || !is_array($state[$eventKey])) {
            $state[$eventKey] = [];
        }

        if (!isset($state[$eventKey]['parts']) || !is_array($state[$eventKey]['parts'])) {
            $state[$eventKey]['parts'] = [];
        }

        $state[$eventKey]['parts'][$partKey] = [
            'confirmed_at' => gmdate('c'),
            'confirmed_by' => $changedBy,
            'completed_count' => (int) ($status['completed_count'] ?? 0),
            'total_count' => (int) ($status['total_count'] ?? 0),
        ];

        $this->writeState($state);

        $allPartsConfirmed = $this->areAllPartsConfirmed($eventId);
        $finalization = [
            'triggered' => false,
            'all_parts_confirmed' => $allPartsConfirmed,
        ];

        if ($allPartsConfirmed) {
            $finalization = $this->finalizeEventIfReady($eventId, $changedBy);
        }

        return [
            'confirmed' => true,
            'already_confirmed' => false,
            'status' => $this->getPartStatus($eventId, $partId),
            'finalization' => $finalization,
        ];
    }

    private function areAllPartsConfirmed(int $eventId): bool {
        $assignments = $this->assignments->findByEventId($eventId);
        $partIds = [];

        foreach ($assignments as $assignment) {
            $partId = (int) ($assignment->part_id ?? 0);
            if ($partId > 0) {
                $partIds[$partId] = $partId;
            }
        }

        if ($partIds === []) {
            return false;
        }

        foreach ($partIds as $partId) {
            if (!$this->isPartConfirmed($eventId, (int) $partId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function finalizeEventIfReady(int $eventId, string $changedBy): array {
        $state = $this->readState();
        $eventKey = (string) $eventId;
        $existing = isset($state[$eventKey]['finalization']) && is_array($state[$eventKey]['finalization'])
            ? $state[$eventKey]['finalization']
            : [];

        if (!empty($existing['completed'])) {
            return [
                'triggered' => false,
                'already_finalized' => true,
                'all_parts_confirmed' => true,
                'winner' => $existing['winner'] ?? null,
                'sent_count' => (int) ($existing['sent_count'] ?? 0),
            ];
        }

        $standings = $this->buildFinalStandings($eventId);
        if ($standings === []) {
            return [
                'triggered' => false,
                'already_finalized' => false,
                'all_parts_confirmed' => true,
                'winner' => null,
                'sent_count' => 0,
            ];
        }

        $recipients = $this->collectRecipients($eventId);
        $winner = $standings[0] ?? null;

        $publication = [
            'headline' => 'BSO Survival - Eindscore en dankwoord',
            'published_at' => gmdate('c'),
            'top_3' => array_slice($standings, 0, 3),
            'final_standings' => $standings,
            'recipients' => $recipients,
            'appreciation_message' => 'Dank aan alle vrijwilligers, ouders, scheidsrechters en leiding. Zonder jullie hulp hadden we dit nooit kunnen verwezenlijken. Het draait om het plezier van de teamleden; we hopen dat iedereen genoten heeft van de survival. Wie nog niet ontgroeid is, zien we graag volgend jaar terug als teamlid. En anders: help gerust mee als vrijwilliger!',
        ];

        if ($this->publications !== null) {
            $this->publications->saveForEvent($eventId, $publication, $changedBy);
        }

        $notificationSummary = [
            'sent_count' => 0,
            'failed_count' => 0,
            'sent_to' => [],
            'failed_to' => [],
        ];

        if ($this->notifications !== null) {
            $notificationSummary = $this->notifications->sendPublicationNotifications($eventId, $publication, $changedBy);
        }

        $state[$eventKey]['finalization'] = [
            'completed' => true,
            'finalized_at' => gmdate('c'),
            'finalized_by' => $changedBy,
            'winner' => $winner,
            'sent_count' => (int) ($notificationSummary['sent_count'] ?? 0),
            'failed_count' => (int) ($notificationSummary['failed_count'] ?? 0),
        ];
        $this->writeState($state);

        return [
            'triggered' => true,
            'already_finalized' => false,
            'all_parts_confirmed' => true,
            'winner' => $winner,
            'sent_count' => (int) ($notificationSummary['sent_count'] ?? 0),
            'failed_count' => (int) ($notificationSummary['failed_count'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFinalStandings(int $eventId): array {
        global $wpdb;
        if (!is_object($wpdb)) {
            return [];
        }

        $assignments = $wpdb->prefix . 'bso_survival_assignments';
        $timeslots = $wpdb->prefix . 'bso_survival_timeslots';
        $teams = $wpdb->prefix . 'bso_survival_teams';
        $scoreEntries = $wpdb->prefix . 'bso_survival_score_entries';

        $sql = $wpdb->prepare(
            "SELECT a.team_id,
                    t.name AS team_name,
                    SUM(COALESCE(se.normalized_points, 0)) AS total_points
             FROM {$assignments} a
             INNER JOIN {$timeslots} ts ON ts.id = a.timeslot_id
             INNER JOIN {$teams} t ON t.id = a.team_id
             LEFT JOIN (
                 SELECT se1.*
                 FROM {$scoreEntries} se1
                 INNER JOIN (
                     SELECT assignment_id, MAX(id) AS latest_id
                     FROM {$scoreEntries}
                     GROUP BY assignment_id
                 ) latest ON latest.latest_id = se1.id
             ) se ON se.assignment_id = a.id
             WHERE ts.event_id = %d
               AND se.id IS NOT NULL
               AND se.entered_by_role <> 'admin_init'
             GROUP BY a.team_id, t.name
             ORDER BY total_points DESC, t.name ASC, a.team_id ASC",
            $eventId
        );

        $rows = $wpdb->get_results($sql) ?: [];
        $standings = [];
        $rank = 1;

        foreach ($rows as $row) {
            $teamId = (int) ($row->team_id ?? 0);
            if ($teamId <= 0) {
                continue;
            }

            $standings[] = [
                'rank' => $rank,
                'team_id' => $teamId,
                'team_name' => (string) ($row->team_name ?? ''),
                'points' => (float) ($row->total_points ?? 0),
            ];
            $rank++;
        }

        return $standings;
    }

    /**
     * @return array<int, string>
     */
    private function collectRecipients(int $eventId): array {
        $recipients = [];

        if ($this->teams !== null) {
            $teams = $this->teams->listTeamsForEvent($eventId);
            foreach ($teams as $team) {
                $email = strtolower(trim((string) ($team->contact_email ?? '')));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[$email] = $email;
                }
            }
        }

        if (function_exists('get_users') && function_exists('user_can')) {
            $users = get_users([
                'fields' => ['user_email'],
            ]);

            foreach ($users as $user) {
                if (!is_object($user)) {
                    continue;
                }

                $email = strtolower(trim((string) ($user->user_email ?? '')));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                if (user_can($user, 'manage_survival_scores') || user_can($user, 'manage_survival_settings')) {
                    $recipients[$email] = $email;
                }
            }
        }

        return array_values($recipients);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function findTies(array $rows): array {
        $groups = [];

        foreach ($rows as $row) {
            if (empty($row['is_completed'])) {
                continue;
            }

            $raw = number_format((float) ($row['raw_value'] ?? 0), 6, '.', '');
            $bonus = number_format((float) ($row['bonus_points'] ?? 0), 6, '.', '');
            $key = $raw . '|' . $bonus;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'raw_value' => (float) $raw,
                    'bonus_points' => (float) $bonus,
                    'teams' => [],
                ];
            }

            $name = trim((string) ($row['team_name'] ?? ''));
            if ($name !== '') {
                $groups[$key]['teams'][$name] = $name;
            }
        }

        $ties = [];
        foreach ($groups as $group) {
            $teams = array_values($group['teams']);
            if (count($teams) <= 1) {
                continue;
            }

            $ties[] = [
                'raw_value' => (float) $group['raw_value'],
                'bonus_points' => (float) $group['bonus_points'],
                'teams' => $teams,
            ];
        }

        return $ties;
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array {
        if (function_exists('get_option')) {
            $stored = get_option(self::OPTION_KEY, []);
            return is_array($stored) ? $stored : [];
        }

        return is_array(self::$memoryState) ? self::$memoryState : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function writeState(array $state): void {
        if (function_exists('update_option')) {
            update_option(self::OPTION_KEY, $state, false);
            return;
        }

        self::$memoryState = $state;
    }

    /**
     * @return array<string, mixed>
     */
    private function readFinalizationState(int $eventId): array {
        $state = $this->readState();
        $eventKey = (string) $eventId;
        $finalization = $state[$eventKey]['finalization'] ?? [];

        return is_array($finalization) ? $finalization : [];
    }

    private function guardPositive(int $value, string $label): void {
        if ($value <= 0) {
            throw new InvalidArgumentException(sprintf('%s moet een positief getal zijn.', $label));
        }
    }
}
