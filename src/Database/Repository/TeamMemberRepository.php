<?php

namespace BSO\Survival\Database\Repository;

class TeamMemberRepository implements TeamMemberRepositoryInterface {
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
    public function create(array $data) {
        $inserted = $this->wpdb->insert($this->tableName(), $data);
        if ($inserted === false) {
            return null;
        }

        $id = isset($this->wpdb->insert_id) ? (int) $this->wpdb->insert_id : 0;
        if ($id <= 0) {
            return null;
        }

        return (object) array_merge(['id' => $id], $data);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function createBatch(array $rows): int {
        $created = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ($this->create($row) !== null) {
                $created++;
            }
        }

        return $created;
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_team_members';
    }
}
