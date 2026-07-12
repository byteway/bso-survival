<?php

namespace BSO\Survival\Database\Repository;

class ScoreEntryRepository implements ScoreEntryRepositoryInterface {
    /** @var object */
    private $wpdb;

    /**
     * @param object|null $wpdb WordPress database object (defaults to global $wpdb)
     */
    public function __construct($wpdb = null) {
        if ($wpdb === null) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
    }

    /**
     * @return object|null
     */
    public function findById(int $id) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);

        $row = $this->withSuppressedDbErrors('findById', function () use ($sql) {
            return $this->wpdb->get_row($sql);
        });

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data) {
        $table = $this->tableName();
        $inserted = $this->withSuppressedDbErrors('insert', function () use ($table, $data) {
            return $this->wpdb->insert($table, $data);
        });

        if ($inserted === false) {
            return null;
        }

        $id = isset($this->wpdb->insert_id) ? (int) $this->wpdb->insert_id : 0;
        if ($id <= 0) {
            return null;
        }

        return (object) array_merge(['id' => $id], $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function updateById(int $id, array $data) {
        $table = $this->tableName();
        $updated = $this->withSuppressedDbErrors('updateById', function () use ($table, $data, $id) {
            return $this->wpdb->update($table, $data, ['id' => $id]);
        });

        if ($updated === false) {
            return null;
        }

        return $this->findById($id);
    }

    /**
     * @return array<int, float>
     */
    public function findLatestRawValuesByPart(int $eventId, int $partId): array {
        if ($eventId <= 0 || $partId <= 0) {
            return [];
        }

        $scoreTable = $this->tableName();
        $assignmentTable = $this->wpdb->prefix . 'bso_survival_assignments';
        $timeslotTable = $this->wpdb->prefix . 'bso_survival_timeslots';

        $sql = $this->wpdb->prepare(
            "SELECT a.team_id, se.raw_value
             FROM {$scoreTable} se
             INNER JOIN {$assignmentTable} a ON a.id = se.assignment_id
             INNER JOIN {$timeslotTable} ts ON ts.id = a.timeslot_id
             INNER JOIN (
                 SELECT assignment_id, MAX(id) AS latest_id
                 FROM {$scoreTable}
                 GROUP BY assignment_id
             ) latest ON latest.latest_id = se.id
             WHERE ts.event_id = %d
               AND a.part_id = %d",
            $eventId,
            $partId
        );

        $rows = $this->withSuppressedDbErrors('findLatestRawValuesByPart', function () use ($sql) {
            return $this->wpdb->get_results($sql);
        }) ?: [];
        $values = [];
        foreach ($rows as $row) {
            $teamId = (int) ($row->team_id ?? 0);
            if ($teamId <= 0) {
                continue;
            }

            $values[$teamId] = (float) ($row->raw_value ?? 0);
        }

        return $values;
    }

    /**
     * @return array<int, float>
     */
    public function findLatestNormalizedPointsByPart(int $eventId, int $partId): array {
        if ($eventId <= 0 || $partId <= 0) {
            return [];
        }

        $scoreTable = $this->tableName();
        $assignmentTable = $this->wpdb->prefix . 'bso_survival_assignments';
        $timeslotTable = $this->wpdb->prefix . 'bso_survival_timeslots';

        $sql = $this->wpdb->prepare(
            "SELECT a.team_id, se.normalized_points
             FROM {$scoreTable} se
             INNER JOIN {$assignmentTable} a ON a.id = se.assignment_id
             INNER JOIN {$timeslotTable} ts ON ts.id = a.timeslot_id
             INNER JOIN (
                 SELECT assignment_id, MAX(id) AS latest_id
                 FROM {$scoreTable}
                 GROUP BY assignment_id
             ) latest ON latest.latest_id = se.id
             WHERE ts.event_id = %d
               AND a.part_id = %d",
            $eventId,
            $partId
        );

        $rows = $this->withSuppressedDbErrors('findLatestNormalizedPointsByPart', function () use ($sql) {
            return $this->wpdb->get_results($sql);
        }) ?: [];
        $values = [];
        foreach ($rows as $row) {
            $teamId = (int) ($row->team_id ?? 0);
            if ($teamId <= 0) {
                continue;
            }

            $values[$teamId] = (float) ($row->normalized_points ?? 0);
        }

        return $values;
    }

    /**
     * @param array<int, int> $assignmentIds
     * @return array<int, int>
     */
    public function findAssignmentIdsWithEntries(array $assignmentIds): array {
        $normalized = [];
        foreach ($assignmentIds as $assignmentId) {
            $id = (int) $assignmentId;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        if ($normalized === []) {
            return [];
        }

        $ids = array_values($normalized);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $table = $this->tableName();

        $sql = $this->wpdb->prepare(
            "SELECT DISTINCT assignment_id
             FROM {$table}
             WHERE assignment_id IN ({$placeholders})",
            ...$ids
        );

        $rows = $this->withSuppressedDbErrors('findAssignmentIdsWithEntries', function () use ($sql) {
            return $this->wpdb->get_col($sql);
        }) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $id = (int) $row;
            if ($id > 0) {
                $result[] = $id;
            }
        }

        return $result;
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_score_entries';
    }

    /**
     * @param callable $callback
     * @return mixed
     */
    private function withSuppressedDbErrors(string $operation, callable $callback) {
        if (!is_object($this->wpdb) || !method_exists($this->wpdb, 'suppress_errors')) {
            return $callback();
        }

        $previous = $this->wpdb->suppress_errors(true);

        try {
            $result = $callback();
            $this->logLastDbError($operation);

            return $result;
        } finally {
            $this->wpdb->suppress_errors((bool) $previous);
        }
    }

    private function logLastDbError(string $operation): void {
        if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
            return;
        }

        if (!is_object($this->wpdb) || !isset($this->wpdb->last_error)) {
            return;
        }

        $error = trim((string) $this->wpdb->last_error);
        if ($error === '') {
            return;
        }

        error_log(sprintf('[bso-survival][ScoreEntryRepository::%s] %s', $operation, $error));
    }
}
