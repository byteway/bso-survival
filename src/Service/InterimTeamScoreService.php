<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use InvalidArgumentException;

class InterimTeamScoreService {
    /** @var PartRuleRepositoryInterface */
    private $rules;

    public function __construct(PartRuleRepositoryInterface $rules) {
        $this->rules = $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTeamOverview(int $eventId, int $teamId): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($teamId <= 0) {
            throw new InvalidArgumentException('team_id must be a positive integer.');
        }

        $teamAssignments = $this->findLatestAssignmentScoresForTeam($eventId, $teamId);
        $latestByPart = $this->indexLatestRowByPart($teamAssignments);
        $partRules = $this->rulesByPartId($eventId);

        $rows = [];
        foreach ($latestByPart as $partId => $baseRow) {
            $rankedPartRows = $this->rankPartRows(
                $this->findLatestTeamScoresForPart($eventId, $partId),
                $partRules[$partId] ?? null
            );

            $selected = null;
            foreach ($rankedPartRows as $candidate) {
                if ((int) ($candidate['team_id'] ?? 0) === $teamId) {
                    $selected = $candidate;
                    break;
                }
            }

            if ($selected === null) {
                $selected = $baseRow;
                $selected['provisional_position'] = 0;
                $selected['interim_score'] = 0;
                $selected['is_completed'] = false;
            }

            $rows[] = [
                'assignment_id' => (int) ($selected['assignment_id'] ?? 0),
                'part_id' => (int) ($selected['part_id'] ?? 0),
                'team_id' => (int) ($selected['team_id'] ?? 0),
                'timeslot_id' => (int) ($selected['timeslot_id'] ?? 0),
                'part_name' => (string) ($selected['part_name'] ?? ''),
                'team_name' => (string) ($selected['team_name'] ?? ''),
                'score_entry_id' => (int) ($selected['score_entry_id'] ?? 0),
                'raw_value' => isset($selected['raw_value']) ? (float) $selected['raw_value'] : 0.0,
                'bonus_points' => isset($selected['bonus_points']) ? (float) $selected['bonus_points'] : 0.0,
                'joker_applied' => !empty($selected['joker_applied']),
                'entered_by_role' => (string) ($selected['entered_by_role'] ?? ''),
                'status' => (string) ($selected['status'] ?? ''),
                'provisional_position' => (int) ($selected['provisional_position'] ?? 0),
                'interim_score' => (int) ($selected['interim_score'] ?? 0),
                'is_completed' => !empty($selected['is_completed']),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $byName = strcasecmp((string) ($left['part_name'] ?? ''), (string) ($right['part_name'] ?? ''));
            if ($byName !== 0) {
                return $byName;
            }

            return ((int) ($left['part_id'] ?? 0)) <=> ((int) ($right['part_id'] ?? 0));
        });

        $completedCount = 0;
        $jokerCount = 0;
        $interimTotal = 0;

        foreach ($rows as $row) {
            if (!empty($row['is_completed'])) {
                $completedCount++;
            }

            if (!empty($row['joker_applied'])) {
                $jokerCount++;
            }

            $interimTotal += (int) ($row['interim_score'] ?? 0);
        }

        return [
            'rows' => $rows,
            'counts' => [
                'completed' => $completedCount,
                'pending' => max(0, count($rows) - $completedCount),
                'joker_count' => $jokerCount,
                'total' => count($rows),
                'interim_total' => $interimTotal,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPartOverview(int $eventId, int $partId): array {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($partId <= 0) {
            throw new InvalidArgumentException('part_id must be a positive integer.');
        }

        $partRules = $this->rulesByPartId($eventId);
        $rule = $partRules[$partId] ?? null;

        $rows = $this->rankPartRows(
            $this->findLatestTeamScoresForPart($eventId, $partId),
            $rule
        );

        $jokerCount = 0;
        $completedCount = 0;
        $interimTotal = 0;
        foreach ($rows as $row) {
            if (!empty($row['is_completed'])) {
                $completedCount++;
            }

            if (!empty($row['joker_applied'])) {
                $jokerCount++;
            }

            $interimTotal += (int) ($row['interim_score'] ?? 0);
        }

        return [
            'rows' => $rows,
            'counts' => [
                'completed' => $completedCount,
                'pending' => max(0, count($rows) - $completedCount),
                'joker_count' => $jokerCount,
                'total' => count($rows),
                'interim_total' => $interimTotal,
            ],
            'sort' => [
                'raw_default_dir' => $this->lowerRawWins($rule) ? 'asc' : 'desc',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findLatestAssignmentScoresForTeam(int $eventId, int $teamId): array {
        global $wpdb;
        if (!is_object($wpdb)) {
            return [];
        }

        $assignments = $wpdb->prefix . 'bso_survival_assignments';
        $timeslots = $wpdb->prefix . 'bso_survival_timeslots';
        $parts = $wpdb->prefix . 'bso_survival_parts';
        $teams = $wpdb->prefix . 'bso_survival_teams';
        $scoreEntries = $wpdb->prefix . 'bso_survival_score_entries';

        $sql = $wpdb->prepare(
            "SELECT a.id AS assignment_id,
                    a.part_id,
                    a.team_id,
                    ts.id AS timeslot_id,
                    p.name AS part_name,
                    t.name AS team_name,
                    se.id AS score_entry_id,
                    se.raw_value,
                    se.bonus_points,
                    se.normalized_points,
                    se.joker_applied,
                    se.entered_by_role,
                    se.status
             FROM {$assignments} a
             INNER JOIN {$timeslots} ts ON ts.id = a.timeslot_id
             INNER JOIN {$parts} p ON p.id = a.part_id
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
               AND a.team_id = %d
             ORDER BY ts.id ASC, a.id ASC",
            $eventId,
            $teamId
        );

        $results = $wpdb->get_results($sql) ?: [];
        $rows = [];

        foreach ($results as $result) {
            $rows[] = [
                'assignment_id' => (int) ($result->assignment_id ?? 0),
                'part_id' => (int) ($result->part_id ?? 0),
                'team_id' => (int) ($result->team_id ?? 0),
                'timeslot_id' => (int) ($result->timeslot_id ?? 0),
                'part_name' => (string) ($result->part_name ?? ''),
                'team_name' => (string) ($result->team_name ?? ''),
                'score_entry_id' => (int) ($result->score_entry_id ?? 0),
                'raw_value' => isset($result->raw_value) ? (float) $result->raw_value : 0.0,
                'bonus_points' => isset($result->bonus_points) ? (float) $result->bonus_points : 0.0,
                'normalized_points' => isset($result->normalized_points) ? (float) $result->normalized_points : 0.0,
                'joker_applied' => (int) ($result->joker_applied ?? 0) === 1,
                'entered_by_role' => (string) ($result->entered_by_role ?? ''),
                'status' => (string) ($result->status ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findLatestAssignmentScoresForPart(int $eventId, int $partId): array {
        global $wpdb;
        if (!is_object($wpdb)) {
            return [];
        }

        $assignments = $wpdb->prefix . 'bso_survival_assignments';
        $timeslots = $wpdb->prefix . 'bso_survival_timeslots';
        $parts = $wpdb->prefix . 'bso_survival_parts';
        $teams = $wpdb->prefix . 'bso_survival_teams';
        $scoreEntries = $wpdb->prefix . 'bso_survival_score_entries';

        $sql = $wpdb->prepare(
            "SELECT a.id AS assignment_id,
                    a.part_id,
                    a.team_id,
                    ts.id AS timeslot_id,
                    p.name AS part_name,
                    t.name AS team_name,
                    se.id AS score_entry_id,
                    se.raw_value,
                    se.bonus_points,
                    se.normalized_points,
                    se.joker_applied,
                    se.entered_by_role,
                    se.status
             FROM {$assignments} a
             INNER JOIN {$timeslots} ts ON ts.id = a.timeslot_id
             INNER JOIN {$parts} p ON p.id = a.part_id
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
               AND a.part_id = %d
             ORDER BY se.id DESC, a.id DESC",
            $eventId,
            $partId
        );

        $results = $wpdb->get_results($sql) ?: [];
        $rows = [];

        foreach ($results as $result) {
            $rows[] = [
                'assignment_id' => (int) ($result->assignment_id ?? 0),
                'part_id' => (int) ($result->part_id ?? 0),
                'team_id' => (int) ($result->team_id ?? 0),
                'timeslot_id' => (int) ($result->timeslot_id ?? 0),
                'part_name' => (string) ($result->part_name ?? ''),
                'team_name' => (string) ($result->team_name ?? ''),
                'score_entry_id' => (int) ($result->score_entry_id ?? 0),
                'raw_value' => isset($result->raw_value) ? (float) $result->raw_value : 0.0,
                'bonus_points' => isset($result->bonus_points) ? (float) $result->bonus_points : 0.0,
                'normalized_points' => isset($result->normalized_points) ? (float) $result->normalized_points : 0.0,
                'joker_applied' => (int) ($result->joker_applied ?? 0) === 1,
                'entered_by_role' => (string) ($result->entered_by_role ?? ''),
                'status' => (string) ($result->status ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findLatestTeamScoresForPart(int $eventId, int $partId): array {
        $rows = $this->findLatestAssignmentScoresForPart($eventId, $partId);

        $latestByTeam = [];
        foreach ($rows as $row) {
            $teamId = (int) ($row['team_id'] ?? 0);
            if ($teamId <= 0) {
                continue;
            }

            if (!isset($latestByTeam[$teamId])) {
                $latestByTeam[$teamId] = $row;
                continue;
            }

            $current = $latestByTeam[$teamId];
            $isNewerTimeslot = (int) ($row['timeslot_id'] ?? 0) > (int) ($current['timeslot_id'] ?? 0);
            $isNewerEntry = (int) ($row['score_entry_id'] ?? 0) > (int) ($current['score_entry_id'] ?? 0);
            if ($isNewerTimeslot || $isNewerEntry) {
                $latestByTeam[$teamId] = $row;
            }
        }

        return array_values($latestByTeam);
    }

    /**
     * @return array<int, object>
     */
    private function rulesByPartId(int $eventId): array {
        $rules = $this->rules->findByEventId($eventId);
        $mapped = [];
        foreach ($rules as $rule) {
            $partId = (int) ($rule->part_id ?? 0);
            if ($partId > 0) {
                $mapped[$partId] = $rule;
            }
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function rankPartRows(array $rows, $rule): array {
        $completed = [];
        $pending = [];
        $lowerRawWins = $this->lowerRawWins($rule);

        foreach ($rows as $row) {
            $isCompleted = $this->isCompletedScore($row);
            $row['is_completed'] = $isCompleted;

            if ($isCompleted) {
                $completed[] = $row;
            } else {
                $row['provisional_position'] = 0;
                $row['interim_score'] = 0;
                $pending[] = $row;
            }
        }

        usort($completed, function (array $left, array $right) use ($lowerRawWins): int {
            $leftRaw = (float) ($left['raw_value'] ?? 0.0);
            $rightRaw = (float) ($right['raw_value'] ?? 0.0);

            $byRaw = $lowerRawWins
                ? ($leftRaw <=> $rightRaw)
                : ($rightRaw <=> $leftRaw);
            if ($byRaw !== 0) {
                return $byRaw;
            }

            $leftBonus = (float) ($left['bonus_points'] ?? 0.0);
            $rightBonus = (float) ($right['bonus_points'] ?? 0.0);
            $byBonus = $rightBonus <=> $leftBonus;
            if ($byBonus !== 0) {
                return $byBonus;
            }

            $byName = strcasecmp((string) ($left['team_name'] ?? ''), (string) ($right['team_name'] ?? ''));
            if ($byName !== 0) {
                return $byName;
            }

            return ((int) ($left['assignment_id'] ?? 0)) <=> ((int) ($right['assignment_id'] ?? 0));
        });

        $completedTotal = count($completed);
        $position = 1;
        foreach ($completed as &$row) {
            $jokerFactor = !empty($row['joker_applied']) ? 2 : 1;
            $weightedPosition = max(1, $completedTotal - $position + 1);
            $row['provisional_position'] = $position;
            $row['weighted_position'] = $weightedPosition;
            $row['interim_score'] = $weightedPosition * 10 * $jokerFactor;
            $position++;
        }
        unset($row);

        usort($pending, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['team_name'] ?? ''), (string) ($right['team_name'] ?? ''));
        });

        return array_merge($completed, $pending);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function indexLatestRowByPart(array $rows): array {
        $latestByPart = [];

        foreach ($rows as $row) {
            $partId = (int) ($row['part_id'] ?? 0);
            if ($partId <= 0) {
                continue;
            }

            if (!isset($latestByPart[$partId])) {
                $latestByPart[$partId] = $row;
                continue;
            }

            $current = $latestByPart[$partId];
            $isNewerTimeslot = (int) ($row['timeslot_id'] ?? 0) > (int) ($current['timeslot_id'] ?? 0);
            $isNewerEntry = (int) ($row['score_entry_id'] ?? 0) > (int) ($current['score_entry_id'] ?? 0);
            if ($isNewerTimeslot || $isNewerEntry) {
                $latestByPart[$partId] = $row;
            }
        }

        return $latestByPart;
    }

    /**
     * @param object|null $rule
     */
    private function lowerRawWins($rule): bool {
        if (!is_object($rule)) {
            return false;
        }

        $tiebreaker = isset($rule->tiebreaker_mode) ? (string) $rule->tiebreaker_mode : '';
        if ($tiebreaker === 'lower_raw_wins') {
            return true;
        }

        if ($tiebreaker === 'higher_raw_wins') {
            return false;
        }

        // Only explicit tiebreaker settings decide raw-score direction.
        // Manual tiebreaker mode defaults to higher raw wins.
        return false;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isCompletedScore(array $row): bool {
        $scoreEntryId = (int) ($row['score_entry_id'] ?? 0);
        $enteredByRole = (string) ($row['entered_by_role'] ?? '');

        return $scoreEntryId > 0 && $enteredByRole !== 'admin_init';
    }
}