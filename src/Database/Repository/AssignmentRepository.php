<?php

namespace BSO\Survival\Database\Repository;

class AssignmentRepository implements AssignmentRepositoryInterface {
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
     * @return object|null
     */
    public function findById(int $id) {
        $assignments = $this->tableName('assignments');
        $timeslots = $this->tableName('timeslots');
        $parts = $this->tableName('parts');
        $teams = $this->tableName('teams');

        $sql = $this->wpdb->prepare(
            "SELECT a.*, ts.event_id, p.name AS part_name, t.name AS team_name
             FROM {$assignments} a
             INNER JOIN {$timeslots} ts ON ts.id = a.timeslot_id
             INNER JOIN {$parts} p ON p.id = a.part_id
             INNER JOIN {$teams} t ON t.id = a.team_id
             WHERE a.id = %d
             LIMIT 1",
            $id
        );

        return $this->wpdb->get_row($sql) ?: null;
    }

    /**
     * @return array<int, object>
     */
    public function findByEventId(int $eventId): array {
        $assignments = $this->tableName('assignments');
        $timeslots = $this->tableName('timeslots');
        $parts = $this->tableName('parts');
        $teams = $this->tableName('teams');

        $sql = $this->wpdb->prepare(
            "SELECT a.*, ts.event_id, p.name AS part_name, t.name AS team_name
             FROM {$assignments} a
             INNER JOIN {$timeslots} ts ON ts.id = a.timeslot_id
             INNER JOIN {$parts} p ON p.id = a.part_id
             INNER JOIN {$teams} t ON t.id = a.team_id
             WHERE ts.event_id = %d
             ORDER BY a.id ASC",
            $eventId
        );

        return $this->wpdb->get_results($sql) ?: [];
    }

    private function tableName(string $suffix): string {
        return $this->wpdb->prefix . 'bso_survival_' . $suffix;
    }
}
