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

    /**
     * @return array<int, object>
     */
    public function findByTeamId(int $teamId): array {
        if ($teamId <= 0) {
            return [];
        }

        $table = $this->tableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE team_id = %d ORDER BY id ASC",
            $teamId
        );

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function deleteByTeamId(int $teamId): int {
        if ($teamId <= 0) {
            return 0;
        }

        $table = $this->tableName();
        $deleted = $this->wpdb->delete($table, ['team_id' => $teamId], ['%d']);

        return $deleted === false ? 0 : (int) $deleted;
    }

    /**
     * @param array<int, string> $names
     */
    public function replaceForTeam(int $teamId, array $names): int {
        if ($teamId <= 0) {
            return 0;
        }

        $this->deleteByTeamId($teamId);

        $rows = [];
        $now = gmdate('Y-m-d H:i:s');
        foreach ($names as $name) {
            $cleanName = trim((string) $name);
            if ($cleanName === '') {
                continue;
            }

            $rows[] = [
                'team_id' => $teamId,
                'name' => $cleanName,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $this->createBatch($rows);
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_team_members';
    }
}
