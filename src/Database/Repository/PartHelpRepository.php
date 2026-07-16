<?php

namespace BSO\Survival\Database\Repository;

class PartHelpRepository implements PartHelpRepositoryInterface {
    /** @var object */
    private $wpdb;

    /** @param object|null $wpdb */
    public function __construct($wpdb = null) {
        if ($wpdb === null) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
    }

    /** @return object|null */
    public function findByPartId(int $partId) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE part_id = %d LIMIT 1", $partId);

        return $this->wpdb->get_row($sql) ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function upsertByPartId(int $partId, array $data) {
        $table = $this->tableName();
        $existing = $this->findByPartId($partId);

        if ($existing === null) {
            $inserted = $this->wpdb->insert($table, array_merge(['part_id' => $partId], $data));
            if ($inserted === false) {
                return null;
            }

            return $this->findByPartId($partId);
        }

        $updated = $this->wpdb->update($table, $data, ['part_id' => $partId]);
        if ($updated === false) {
            return null;
        }

        return $this->findByPartId($partId);
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_part_help';
    }
}
