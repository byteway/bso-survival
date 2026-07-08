<?php

namespace BSO\Survival\Database\Repository;

class DashboardMessageRepository implements DashboardMessageRepositoryInterface {
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

    public function findByEventId(int $eventId, int $limit = 20): array {
        return $this->findByScope($eventId, 'all', false, $limit);
    }

    public function findByScope(int $eventId, string $scope = 'all', bool $activeOnly = false, int $limit = 20, int $offset = 0): array {
        $table = $this->tableName();
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $now = gmdate('Y-m-d H:i:s');

        $whereScope = $this->buildScopeWhereClause($scope);
        $activeClause = $activeOnly
            ? " AND status = 'actief' AND (visible_from IS NULL OR visible_from <= %s) AND (visible_until IS NULL OR visible_until >= %s)"
            : '';
        $orderClause = " ORDER BY
            CASE type
                WHEN 'urgent' THEN 400
                WHEN 'warning' THEN 300
                WHEN 'info' THEN 200
                WHEN 'success' THEN 100
                ELSE 50
            END DESC,
            updated_at DESC,
            id DESC";

        $template = "SELECT * FROM {$table} WHERE {$whereScope}{$activeClause}{$orderClause} LIMIT %d OFFSET %d";
        if ($scope === 'global') {
            if ($activeOnly) {
                $sql = $this->wpdb->prepare($template, $now, $now, $limit, $offset);
            } else {
                $sql = $this->wpdb->prepare($template, $limit, $offset);
            }
        } else {
            if ($activeOnly) {
                $sql = $this->wpdb->prepare($template, $eventId, $now, $now, $limit, $offset);
            } else {
                $sql = $this->wpdb->prepare($template, $eventId, $limit, $offset);
            }
        }

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function countByScope(int $eventId, string $scope = 'all', bool $activeOnly = false): int {
        $table = $this->tableName();
        $whereScope = $this->buildScopeWhereClause($scope);
        $now = gmdate('Y-m-d H:i:s');
        $activeClause = $activeOnly
            ? " AND status = 'actief' AND (visible_from IS NULL OR visible_from <= %s) AND (visible_until IS NULL OR visible_until >= %s)"
            : '';

        $template = "SELECT COUNT(*) FROM {$table} WHERE {$whereScope}{$activeClause}";
        if ($scope === 'global') {
            if ($activeOnly) {
                $sql = $this->wpdb->prepare($template, $now, $now);
            } else {
                $sql = $template;
            }
        } else {
            if ($activeOnly) {
                $sql = $this->wpdb->prepare($template, $eventId, $now, $now);
            } else {
                $sql = $this->wpdb->prepare($template, $eventId);
            }
        }

        return (int) $this->wpdb->get_var($sql);
    }

    public function findActiveByEventId(int $eventId, int $limit = 5): array {
        return $this->findByScope($eventId, 'all', true, $limit);
    }

    public function findById(int $id) {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);

        return $this->wpdb->get_row($sql) ?: null;
    }

    public function create(array $data) {
        $table = $this->tableName();
        $inserted = $this->wpdb->insert($table, $data);

        if ($inserted === false) {
            return null;
        }

        $id = isset($this->wpdb->insert_id) ? (int) $this->wpdb->insert_id : 0;
        if ($id <= 0) {
            return null;
        }

        return $this->findById($id);
    }

    public function updateStatus(int $id, string $status) {
        return $this->updateStatusForEvent($id, 0, $status);
    }

    public function updateStatusForEvent(int $id, int $eventId, string $status) {
        $table = $this->tableName();

        $where = ['id' => $id];
        if ($eventId > 0) {
            $where['event_id'] = $eventId;
        }

        $updated = $this->wpdb->update($table, [
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], $where, ['%s', '%s'], array_fill(0, count($where), '%d'));

        if ($updated === false) {
            return null;
        }

        return $this->findById($id);
    }

    public function updateById(int $id, array $data) {
        return $this->updateByIdForEvent($id, 0, $data);
    }

    public function updateByIdForEvent(int $id, int $eventId, array $data) {
        $table = $this->tableName();

        if ($data === []) {
            return $this->findById($id);
        }

        $where = ['id' => $id];
        if ($eventId > 0) {
            $where['event_id'] = $eventId;
        }

        $updated = $this->wpdb->update($table, $data, $where);
        if ($updated === false) {
            return null;
        }

        return $this->findById($id);
    }

    public function deleteById(int $id): bool {
        return $this->deleteByIdForEvent($id, 0);
    }

    public function deleteByIdForEvent(int $id, int $eventId): bool {
        $table = $this->tableName();
        $where = ['id' => $id];
        if ($eventId > 0) {
            $where['event_id'] = $eventId;
        }

        $deleted = $this->wpdb->delete($table, $where);
        return $deleted !== false && (int) $deleted > 0;
    }

    private function buildScopeWhereClause(string $scope): string {
        switch ($scope) {
            case 'event':
                return 'event_id = %d';
            case 'global':
                return "visibility = 'global'";
            case 'all':
            default:
                return "(event_id = %d OR visibility = 'global')";
        }
    }

    private function tableName(): string {
        return $this->wpdb->prefix . 'bso_survival_messages';
    }
}
