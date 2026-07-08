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

        return $this->wpdb->get_row($sql) ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data) {
        $table = $this->tableName();
        $inserted = $this->wpdb->insert($table, $data);

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
        $updated = $this->wpdb->update($table, $data, ['id' => $id]);

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

        $sql = $this->wpdb->prepare(
            "SELECT a.team_id, se.raw_value
             FROM {$scoreTable} se
             INNER JOIN {$assignmentTable} a ON a.id = se.assignment_id
             INNER JOIN (
                 SELECT assignment_id, MAX(id) AS latest_id
                 FROM {$scoreTable}
                 GROUP BY assignment_id
             ) latest ON latest.latest_id = se.id
             WHERE a.event_id = %d
               AND a.part_id = %d",
            $eventId,
            $partId
        );

        $rows = $this->wpdb->get_results($sql) ?: [];
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

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_score_entries';
    }
}
