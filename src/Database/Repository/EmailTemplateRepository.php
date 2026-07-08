<?php

namespace BSO\Survival\Database\Repository;

class EmailTemplateRepository implements EmailTemplateRepositoryInterface {
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
    public function findByKey(string $templateKey) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE template_key = %s LIMIT 1", $templateKey);

        return $this->wpdb->get_row($sql) ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function upsertByKey(string $templateKey, array $data) {
        $existing = $this->findByKey($templateKey);
        $table = $this->tableName();

        if ($existing === null) {
            $inserted = $this->wpdb->insert($table, array_merge(['template_key' => $templateKey], $data));
            if ($inserted === false) {
                return null;
            }

            $id = isset($this->wpdb->insert_id) ? (int) $this->wpdb->insert_id : 0;
            if ($id <= 0) {
                return null;
            }

            return (object) array_merge(['id' => $id, 'template_key' => $templateKey], $data);
        }

        $updated = $this->wpdb->update($table, $data, ['template_key' => $templateKey]);
        if ($updated === false) {
            return null;
        }

        return $this->findByKey($templateKey);
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_email_templates';
    }
}
