<?php

namespace BSO\Survival\Database\Repository;

class PartAdminRepository implements PartAdminRepositoryInterface {
    /** @var object */
    private $wpdb;

    /** @param object|null $wpdb */
    public function __construct($wpdb = null) {
        if ($wpdb === null) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
    }

    public function findAll(): array {
        $table = $this->tableName();
        $sql = "SELECT * FROM {$table} ORDER BY name ASC, id ASC";

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function findByIds(array $partIds): array {
        $normalized = array_values(array_unique(array_filter(array_map('intval', $partIds), static function (int $id): bool {
            return $id > 0;
        })));

        if ($normalized === []) {
            return [];
        }

        $table = $this->tableName();
        $placeholders = implode(',', array_fill(0, count($normalized), '%d'));
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id IN ({$placeholders})", ...$normalized);

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function findByEventId(int $eventId): array {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d ORDER BY name ASC, id ASC",
            $eventId
        );

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function findById(int $partId) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $partId);

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

    public function updateById(int $partId, array $data) {
        $table = $this->tableName();
        $updated = $this->wpdb->update($table, $data, ['id' => $partId]);
        if ($updated === false) {
            return null;
        }

        return $this->findById($partId);
    }

    public function markDeleted(int $partId): bool {
        $table = $this->tableName();
        $updated = $this->wpdb->update(
            $table,
            [
                'status' => 'verwijderd',
                'event_id' => null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $partId],
            ['%s', '%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    public function assignToEvent(int $partId, ?int $eventId): bool {
        $table = $this->tableName();
        $updated = $this->wpdb->update(
            $table,
            [
                'event_id' => $eventId,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $partId],
            ['%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_parts';
    }
}
