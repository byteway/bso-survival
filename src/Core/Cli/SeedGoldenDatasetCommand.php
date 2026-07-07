<?php

namespace BSO\Survival\Core\Cli;

use BSO\Survival\Database\GoldenDatasetSeeder;
use RuntimeException;

class SeedGoldenDatasetCommand {
    /**
     * Seed the golden test dataset into the v2 tables.
     *
     * ## OPTIONS
     *
     * [--truncate]
     * : Clear events, parts and teams tables before seeding.
     */
    public function __invoke(array $args, array $assocArgs): void {
        if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
            \WP_CLI::error('Seeding is only allowed when WP_DEBUG=true.');
            return;
        }

        $truncate = array_key_exists('truncate', $assocArgs);

        try {
            $result = GoldenDatasetSeeder::seed($truncate);
        } catch (RuntimeException $exception) {
            \WP_CLI::error($exception->getMessage());
            return;
        }

        \WP_CLI::success(
            sprintf(
                'Golden dataset %s seeded: event=%d, parts=%d, teams=%d',
                $result['dataset_version'],
                $result['event_id'],
                $result['parts_seeded'],
                $result['teams_seeded']
            )
        );
    }
}
