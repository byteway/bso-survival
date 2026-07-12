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

    /** @return object|null */
    public function findByEventId(int $eventId) {
        if ($eventId <= 0) {
            return null;
        }

        $table = $this->tableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d ORDER BY id DESC LIMIT 1",
            $eventId
        );

        return $this->wpdb->get_row($sql) ?: null;
    }

    /** @return object|null */
    public function saveForEvent(int $eventId, string $opensAt, string $closesAt, string $status = 'open') {
        if ($eventId <= 0) {
            return null;
        }

        $table = $this->tableName();
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->findByEventId($eventId);

        $data = [
            'event_id' => $eventId,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'status' => $status !== '' ? $status : 'closed',
            'updated_at' => $now,
        ];

        if ($existing !== null && isset($existing->id)) {
            $updated = $this->wpdb->update(
                $table,
                $data,
                ['id' => (int) $existing->id],
                ['%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                return null;
            }

            return $this->findByEventId($eventId);
        }

        $data['created_at'] = $now;

        $inserted = $this->wpdb->insert(
            $table,
            $data,
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return null;
        }

        return $this->findByEventId($eventId);
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_registration_windows';
    }
}
