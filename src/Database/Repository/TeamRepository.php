<?php

namespace BSO\Survival\Database\Repository;

class TeamRepository implements TeamRepositoryInterface {
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
     * @return array<int, object>
     */
    public function findByEventId(int $eventId): array {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d ORDER BY id ASC",
            $eventId
        );

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function countByEventId(int $eventId): int {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d", $eventId);
        $count = $this->wpdb->get_var($sql);

        return (int) $count;
    }

    /** @return object|null */
    public function findByEventIdAndName(int $eventId, string $name) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d AND name = %s LIMIT 1",
            $eventId,
            $name
        );

        return $this->wpdb->get_row($sql) ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function create(array $data) {
        $table = $this->tableName();
        $inserted = $this->wpdb->insert($table, $data);

        if ($inserted === false) {
            return null;
        }

        $id = isset($this->wpdb->insert_id) ? (int) $this->wpdb->insert_id : 0;
        if ($id <= 0) {
            return null;
        }

        return $this->findById($id);
    }

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function updateById(int $id, array $data) {
        if ($id <= 0) {
            return null;
        }

        $table = $this->tableName();
        $updated = $this->wpdb->update($table, $data, ['id' => $id]);
        if ($updated === false) {
            return null;
        }

        return $this->findById($id);
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_teams';
    }
}
