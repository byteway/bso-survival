<?php

namespace BSO\Survival\Database\Repository;

class EventRepository {
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
     * @return array<int, object>
     */
    public function findAll(): array {
        $table = $this->tableName();
        $sql = "SELECT * FROM {$table} ORDER BY event_date ASC, id ASC";

        return $this->wpdb->get_results($sql) ?: [];
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
    public function findByStatus(string $status): array {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = %s ORDER BY event_date ASC, id ASC",
            $status
        );

        return $this->wpdb->get_results($sql) ?: [];
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_events';
    }
}
