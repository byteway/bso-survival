<?php

namespace BSO\Survival\Database\Repository;

class TeamRepository {
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

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_teams';
    }
}
