<?php

namespace BSO\Survival\Database\Repository;

class EventAdminRepository implements EventAdminRepositoryInterface {
    /** @var object */
    private $wpdb;

    /** @param object|null $wpdb */
    public function __construct($wpdb = null) {
        if ($wpdb === null) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
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

        return (object) array_merge(['id' => $id], $data);
    }

    public function updateById(int $eventId, array $data) {
        $table = $this->tableName();
        $updated = $this->wpdb->update($table, $data, ['id' => $eventId]);

        if ($updated === false) {
            return null;
        }

        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $eventId);
        return $this->wpdb->get_row($sql) ?: null;
    }

    public function markDeleted(int $eventId): bool {
        $table = $this->tableName();

        $updated = $this->wpdb->update(
            $table,
            [
                'status' => 'verwijderd',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $eventId],
            ['%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_events';
    }
}
