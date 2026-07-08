<?php

namespace BSO\Survival\Database\Repository;

class EventPublicationRepository implements EventPublicationRepositoryInterface {
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
    public function findByEventId(int $eventId) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE event_id = %d LIMIT 1", $eventId);

        return $this->wpdb->get_row($sql) ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function upsertByEventId(int $eventId, array $data) {
        $existing = $this->findByEventId($eventId);
        $table = $this->tableName();

        if ($existing === null) {
            $inserted = $this->wpdb->insert($table, array_merge(['event_id' => $eventId], $data));
            if ($inserted === false) {
                return null;
            }

            $id = isset($this->wpdb->insert_id) ? (int) $this->wpdb->insert_id : 0;
            if ($id <= 0) {
                return null;
            }

            return (object) array_merge(['id' => $id, 'event_id' => $eventId], $data);
        }

        $updated = $this->wpdb->update($table, $data, ['event_id' => $eventId]);
        if ($updated === false) {
            return null;
        }

        return $this->findByEventId($eventId);
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_event_publications';
    }
}
