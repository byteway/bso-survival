<?php

namespace BSO\Survival\Core\Cli;

use BSO\Survival\Database\GoldenDatasetSeeder;
use RuntimeException;

class SeedDemoScoresCommand {
    /**
     * Seed deterministic demo scores for specific timeslot ordinals.
     *
     * Scores are read from GoldenDataset::demoScores() and matched to
     * assignments by part name + team name for the given event.
     * Existing admin_init placeholder entries are updated to status=final.
     *
     * ## OPTIONS
     *
     * [--slot=<n>]
     * : Comma-separated timeslot ordinals to seed (e.g. 1,6,9,12).
    *   If omitted, all timeslots of the selected event are seeded.
     *
     * [--event-id=<n>]
     * : Target event ID. Defaults to the most recently created event.
     *
     * ## EXAMPLES
     *
     *     wp bso-survival seed-demo-scores
     *     wp bso-survival seed-demo-scores --slot=1
    *     wp bso-survival seed-demo-scores --slot=1,6,9,12 --event-id=7
     */
    public function __invoke(array $args, array $assocArgs): void {
        if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
            \WP_CLI::error('Demo score seeding is only allowed when WP_DEBUG=true.');
            return;
        }

        $eventId = isset($assocArgs['event-id'])
            ? (int) $assocArgs['event-id']
            : $this->resolveLatestEventId();

        if ($eventId <= 0) {
            \WP_CLI::error('No event found. Please specify --event-id=<n>.');
            return;
        }

        if (isset($assocArgs['slot'])) {
            $slotArg = (string) $assocArgs['slot'];
            $slotNumbers = array_values(
                array_filter(
                    array_map('intval', explode(',', $slotArg)),
                    static fn (int $n): bool => $n > 0
                )
            );

            if (empty($slotNumbers)) {
                \WP_CLI::error('No valid slot numbers provided.');
                return;
            }
        } else {
            $slotNumbers = $this->resolveAllTimeslotOrdinals($eventId);
            if (empty($slotNumbers)) {
                \WP_CLI::error(sprintf('No timeslots found for event #%d.', $eventId));
                return;
            }
        }

        \WP_CLI::log(sprintf('Seeding demo scores for event #%d, slots: %s …', $eventId, implode(', ', $slotNumbers)));

        try {
            $result = GoldenDatasetSeeder::seedDemoScores($slotNumbers, $eventId);
        } catch (RuntimeException $exception) {
            \WP_CLI::error($exception->getMessage());
            return;
        }

        \WP_CLI::success(
            sprintf(
                'Done — event #%d, slots [%s]: %d updated, %d inserted, %d skipped.',
                $eventId,
                implode(', ', $slotNumbers),
                $result['updated'],
                $result['inserted'],
                $result['skipped']
            )
        );
    }

    private function resolveLatestEventId(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'bso_survival_events';
        $id    = $wpdb->get_var("SELECT id FROM {$table} ORDER BY created_at DESC LIMIT 1");

        return $id !== null ? (int) $id : 0;
    }

    /**
     * @return list<int>
     */
    private function resolveAllTimeslotOrdinals(int $eventId): array {
        global $wpdb;

        $table = $wpdb->prefix . 'bso_survival_timeslots';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE event_id = %d ORDER BY start_at ASC",
                $eventId
            )
        );

        $ordinals = [];
        foreach ($rows as $index => $row) {
            $ordinals[] = $index + 1;
        }

        return $ordinals;
    }
}
