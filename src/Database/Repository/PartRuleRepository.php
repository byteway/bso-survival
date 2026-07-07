<?php

namespace BSO\Survival\Database\Repository;

class PartRuleRepository implements PartRuleRepositoryInterface {
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
    public function findByPartId(int $partId) {
        $table = $this->partRulesTableName();
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE part_id = %d LIMIT 1",
            $partId
        );

        return $this->wpdb->get_row($sql) ?: null;
    }

    /**
     * @return array<int, object>
     */
    public function findByEventId(int $eventId): array {
        $rulesTable = $this->partRulesTableName();
        $partsTable = $this->partsTableName();

        $sql = $this->wpdb->prepare(
            "SELECT p.id AS part_id, p.name AS part_name, r.scoring_mode, r.unit, r.tiebreaker_mode, r.scoring_config
             FROM {$partsTable} p
             LEFT JOIN {$rulesTable} r ON r.part_id = p.id
             WHERE p.event_id = %d
             ORDER BY p.id ASC",
            $eventId
        );

        return $this->wpdb->get_results($sql) ?: [];
    }

    public function upsertForPart(int $partId, string $scoringMode, string $unit, string $tiebreakerMode, string $scoringConfig): bool {
        $table = $this->partRulesTableName();
        $now = gmdate('Y-m-d H:i:s');

        $existing = $this->findByPartId($partId);
        if ($existing !== null) {
            $updated = $this->wpdb->update(
                $table,
                [
                    'scoring_mode' => $scoringMode,
                    'unit' => $unit,
                    'tiebreaker_mode' => $tiebreakerMode,
                    'scoring_config' => $scoringConfig,
                    'updated_at' => $now,
                ],
                ['part_id' => $partId],
                ['%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            return $updated !== false;
        }

        $inserted = $this->wpdb->insert(
            $table,
            [
                'part_id' => $partId,
                'scoring_mode' => $scoringMode,
                'unit' => $unit,
                'tiebreaker_mode' => $tiebreakerMode,
                'scoring_config' => $scoringConfig,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $inserted !== false;
    }

    private function partRulesTableName(): string {
        return $this->wpdb->prefix . 'bso_survival_part_rules';
    }

    private function partsTableName(): string {
        return $this->wpdb->prefix . 'bso_survival_parts';
    }
}
