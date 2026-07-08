<?php

namespace BSO\Survival\Database\Repository;

class EmailOutboxRepository implements EmailOutboxRepositoryInterface {
    /** @var object */
    private $wpdb;

    /** @param object|null $wpdb */
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

    /** @return object|null */
    public function findByDedupeKey(string $dedupeKey) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE dedupe_key = %s LIMIT 1", $dedupeKey);

        return $this->wpdb->get_row($sql) ?: null;
    }

    /**
     * @return array<int, object>
     */
    public function findDue(string $nowUtc, int $limit): array {
        $table = $this->tableName();
        $safeLimit = max(1, $limit);
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE status IN ('queued', 'retry') AND next_attempt_at <= %s ORDER BY next_attempt_at ASC, id ASC LIMIT %d",
            $nowUtc,
            $safeLimit
        );

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function markSent(int $id, string $sentAtUtc): bool {
        $table = $this->tableName();

        $updated = $this->wpdb->update(
            $table,
            [
                'status' => 'sent',
                'sent_at' => $sentAtUtc,
                'updated_at' => $sentAtUtc,
            ],
            ['id' => $id]
        );

        return $updated !== false;
    }

    public function markForRetry(int $id, int $attemptCount, string $nextAttemptAtUtc, string $lastError): bool {
        $table = $this->tableName();
        $updated = $this->wpdb->update(
            $table,
            [
                'status' => 'retry',
                'attempt_count' => $attemptCount,
                'next_attempt_at' => $nextAttemptAtUtc,
                'last_error' => $lastError,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $id]
        );

        return $updated !== false;
    }

    public function markFailed(int $id, int $attemptCount, string $lastError): bool {
        $table = $this->tableName();
        $updated = $this->wpdb->update(
            $table,
            [
                'status' => 'failed',
                'attempt_count' => $attemptCount,
                'last_error' => $lastError,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $id]
        );

        return $updated !== false;
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_email_outbox';
    }
}
