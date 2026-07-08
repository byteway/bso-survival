<?php

namespace BSO\Survival\Database\Repository;

class RegistrationWindowRepository implements RegistrationWindowRepositoryInterface {
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
    public function findOpenForEventAt(int $eventId, string $momentUtc) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d AND status = %s AND opens_at <= %s AND closes_at >= %s ORDER BY opens_at ASC LIMIT 1",
            $eventId,
            'open',
            $momentUtc,
            $momentUtc
        );

        return $this->wpdb->get_row($sql) ?: null;
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_registration_windows';
    }
}
