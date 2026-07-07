<?php

namespace BSO\Survival\Database;

use RuntimeException;

class GoldenDatasetSeeder {
    private const DATASET_CLASS = 'BSO\\Survival\\Tests\\Support\\GoldenDataset';

    /**
     * Seed golden dataset into core v2 tables.
     *
     * @return array<string, int|string>
     */
    public static function seed(bool $truncate = false): array {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('WordPress database object is not available.');
        }

        $dataset = self::loadDataset();
        $event = $dataset['event'];
        $parts = $dataset['parts'];
        $teams = $dataset['teams'];

        $eventsTable = $wpdb->prefix . 'bso_survival_events';
        $partsTable = $wpdb->prefix . 'bso_survival_parts';
        $teamsTable = $wpdb->prefix . 'bso_survival_teams';

        $now = gmdate('Y-m-d H:i:s');

        if ($truncate) {
            $wpdb->query("DELETE FROM {$teamsTable}");
            $wpdb->query("DELETE FROM {$partsTable}");
            $wpdb->query("DELETE FROM {$eventsTable}");
        }

        $wpdb->replace(
            $eventsTable,
            [
                'id' => (int) $event['id'],
                'name' => (string) $event['name'],
                'event_date' => (string) $event['event_date'],
                'status' => (string) $event['status'],
                'meta_data' => wp_json_encode(['seed' => 'golden-dataset-v1']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $partsCount = 0;
        foreach ($parts as $part) {
            $inserted = $wpdb->replace(
                $partsTable,
                [
                    'id' => (int) $part['id'],
                    'event_id' => (int) $event['id'],
                    'name' => (string) $part['name'],
                    'latitude' => null,
                    'longitude' => null,
                    'status' => (string) $part['status'],
                    'meta_data' => wp_json_encode(['seed' => true]),
                    'scheduling_constraints' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s']
            );

            if ($inserted !== false) {
                $partsCount++;
            }
        }

        $teamsCount = 0;
        foreach ($teams as $team) {
            $teamName = (string) $team['name'];
            $teamNumber = str_pad((string) $team['id'], 3, '0', STR_PAD_LEFT);

            $inserted = $wpdb->replace(
                $teamsTable,
                [
                    'id' => (int) $team['id'],
                    'event_id' => (int) $event['id'],
                    'name' => $teamName,
                    'contact_name' => 'Contact ' . $teamName,
                    'contact_phone' => '06' . str_pad($teamNumber, 8, '0', STR_PAD_LEFT),
                    'contact_email' => strtolower($teamName) . '@example.test',
                    'status' => (string) $team['status'],
                    'meta_data' => wp_json_encode(['seed' => true]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if ($inserted !== false) {
                $teamsCount++;
            }
        }

        return [
            'dataset_version' => (string) $dataset['dataset_version'],
            'event_id' => (int) $event['id'],
            'parts_seeded' => $partsCount,
            'teams_seeded' => $teamsCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadDataset(): array {
        if (!class_exists(self::DATASET_CLASS)) {
            $pluginRoot = dirname((string) BSO_SURVIVAL_PLUGIN_FILE);
            $datasetFile = $pluginRoot . '/tests/Support/GoldenDataset.php';
            if (file_exists($datasetFile)) {
                require_once $datasetFile;
            }
        }

        if (!class_exists(self::DATASET_CLASS)) {
            throw new RuntimeException('Golden dataset class not found. Ensure tests/Support/GoldenDataset.php is present.');
        }

        $dataset = call_user_func([self::DATASET_CLASS, 'v1']);
        if (!is_array($dataset)) {
            throw new RuntimeException('Golden dataset payload is invalid.');
        }

        return $dataset;
    }
}
