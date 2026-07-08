<?php

namespace BSO\Survival\Database\Repository;

class DashboardMessageRepository implements DashboardMessageRepositoryInterface {
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

    public function findByEventId(int $eventId, int $limit = 20): array {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d ORDER BY updated_at DESC, id DESC LIMIT %d",
            $eventId,
            $limit
        );

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function findActiveByEventId(int $eventId, int $limit = 5): array {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d AND status = %s ORDER BY updated_at DESC, id DESC LIMIT %d",
            $eventId,
            'actief',
            $limit
        );

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function findById(int $id) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);

        return $this->wpdb->get_row($sql) ?: null;
    }

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

    public function updateStatus(int $id, string $status) {
        $table = $this->tableName();
        $updated = $this->wpdb->update($table, [
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id], ['%s', '%s'], ['%d']);

        if ($updated === false) {
            return null;
        }

        return $this->findById($id);
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_messages';
    }
}
