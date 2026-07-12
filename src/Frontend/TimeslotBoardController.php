<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\InterimTeamScoreService;
use BSO\Survival\Service\PartService;
use InvalidArgumentException;
use Throwable;

class TimeslotBoardController {
    public const DEFAULT_EVENT_ID = 1;

    /** @var EventService */
    private $eventService;

    /** @var PartService */
    private $partService;

    /** @var InterimTeamScoreService */
    private $scores;

    public function __construct(EventService $eventService, PartService $partService, InterimTeamScoreService $scores) {
        $this->eventService = $eventService;
        $this->partService = $partService;
        $this->scores = $scores;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render(array $atts = []): string {
        wp_enqueue_style('bso-survival-frontend');

        $attributes = shortcode_atts([
            'title' => __('Tijdslot overzicht', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
            'part_id' => 0,
        ], $atts, 'bso_survival_timeslot_board');

        $eventId = (int) $attributes['event_id'];
        $selectedPartId = isset($_GET['part_id']) ? (int) $_GET['part_id'] : (int) $attributes['part_id'];
        $selectedPartId = max(0, $selectedPartId);

        try {
            $event = $this->eventService->getEvent($eventId);
            if ($event === null) {
                throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
            }

            $parts = $this->partService->listPartsForEvent($eventId);
            if ($parts === []) {
                throw new InvalidArgumentException(sprintf('Geen onderdelen gevonden voor event %d.', $eventId));
            }

            if ($selectedPartId <= 0) {
                $selectedPartId = (int) ($parts[0]->id ?? 0);
            }

            $selectedPart = null;
            foreach ($parts as $part) {
                if ((int) ($part->id ?? 0) === $selectedPartId) {
                    $selectedPart = $part;
                    break;
                }
            }

            if ($selectedPart === null) {
                $selectedPartId = (int) ($parts[0]->id ?? 0);
                $selectedPart = $parts[0] ?? null;
            }

            if ($selectedPart === null) {
                throw new InvalidArgumentException(sprintf('Onderdeel %d not found for event %d.', $selectedPartId, $eventId));
            }

            $timeslotRows = $this->scores->getTimeslotBoardRows($eventId, $selectedPartId);
        } catch (Throwable $exception) {
            $message = sprintf(__('Tijdslot overzicht niet beschikbaar voor event_id %d.', 'bso-survival'), $eventId);

            if (function_exists('do_action')) {
                do_action('bso_survival_timeslot_board_render_error', $message, $eventId);
            }

            return sprintf(
                '<section class="bso-survival-timeslot-board"><p>%s</p></section>',
                esc_html($message)
            );
        }

        $gridRows = $this->buildGridRows($timeslotRows ?? []);
        $hasScoreRows = $gridRows !== [];
        $title = (string) $attributes['title'];

        ob_start();
        include __DIR__ . '/../../templates/frontend-timeslot-board.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildGridRows(array $rows): array {
        $grouped = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $timeslotId = (int) ($row['timeslot_id'] ?? 0);
            if ($timeslotId <= 0) {
                continue;
            }

            if (!isset($grouped[$timeslotId])) {
                $grouped[$timeslotId] = [
                    'timeslot_id' => $timeslotId,
                    'part_name' => (string) ($row['part_name'] ?? ''),
                    'start_label' => '',
                    'end_label' => '',
                    'teams' => [],
                ];
            }

            $grouped[$timeslotId]['part_name'] = (string) ($row['part_name'] ?? $grouped[$timeslotId]['part_name']);
            $teamId = (int) ($row['team_id'] ?? 0);
            if ($teamId > 0) {
                $alreadyAdded = false;
                foreach ($grouped[$timeslotId]['teams'] as $existingTeam) {
                    if ((int) ($existingTeam['team_id'] ?? 0) === $teamId) {
                        $alreadyAdded = true;
                        break;
                    }
                }

                if (!$alreadyAdded) {
                    $grouped[$timeslotId]['teams'][] = [
                        'team_id' => $teamId,
                        'team_name' => (string) ($row['team_name'] ?? ''),
                        'score_entry_id' => (int) ($row['score_entry_id'] ?? 0),
                        'is_completed' => !empty($row['is_completed']),
                    ];
                }
            }
        }

        $normalized = array_values($grouped);
        usort($normalized, static function (array $left, array $right): int {
            return ((int) ($left['timeslot_id'] ?? 0)) <=> ((int) ($right['timeslot_id'] ?? 0));
        });

        $slotNumber = 1;
        foreach ($normalized as &$row) {
            $teams = $row['teams'];
            usort($teams, static function (array $left, array $right): int {
                return strcasecmp((string) ($left['team_name'] ?? ''), (string) ($right['team_name'] ?? ''));
            });

            $row['teams'] = $teams;
            $row['slot_number'] = $slotNumber;
            $row['start_label'] = '';
            $row['end_label'] = '';

            if (isset($teams[0])) {
                $row['team_a'] = $teams[0];
            } else {
                $row['team_a'] = ['team_id' => 0, 'team_name' => '', 'score_entry_id' => 0, 'is_completed' => false];
            }

            if (isset($teams[1])) {
                $row['team_b'] = $teams[1];
            } else {
                $row['team_b'] = ['team_id' => 0, 'team_name' => '', 'score_entry_id' => 0, 'is_completed' => false];
            }

            $slotNumber++;
        }
        unset($row);

        return $normalized;
    }
}
