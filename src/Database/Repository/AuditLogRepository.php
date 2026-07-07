<?php

namespace BSO\Survival\Database\Repository;

class AuditLogRepository implements AuditLogRepositoryInterface {
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

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_audit_logs';
    }
}
