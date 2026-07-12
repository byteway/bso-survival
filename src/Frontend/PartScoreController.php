<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\InterimTeamScoreService;
use BSO\Survival\Service\PartService;
use BSO\Survival\Support\Capabilities;
use InvalidArgumentException;
use Throwable;

class PartScoreController {
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
        wp_enqueue_script('bso-survival-frontend-score');

        $attributes = shortcode_atts([
            'title' => __('Onderdeel Score', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
            'part_id' => 0,
        ], $atts, 'bso_survival_part_score');

        $eventId = (int) $attributes['event_id'];
        $selectedPartId = isset($_GET['part_id']) ? (int) $_GET['part_id'] : (int) $attributes['part_id'];

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

            $part = null;
            foreach ($parts as $partOption) {
                if ((int) ($partOption->id ?? 0) === $selectedPartId) {
                    $part = $partOption;
                    break;
                }
            }

            if ($part === null) {
                $selectedPartId = (int) ($parts[0]->id ?? 0);
                $part = $parts[0] ?? null;
            }

            if ($part === null) {
                throw new InvalidArgumentException(sprintf('Onderdeel %d hoort niet bij event %d.', $selectedPartId, $eventId));
            }

            $overview = $this->scores->getPartOverview($eventId, $selectedPartId);
        } catch (Throwable $exception) {
            $message = sprintf(__('Onderdeelscore niet beschikbaar voor event_id %d en part_id %d.', 'bso-survival'), $eventId, $selectedPartId);

            if (function_exists('do_action')) {
                do_action('bso_survival_parts_render_error', $message, $eventId);
            }

            return sprintf(
                '<section class="bso-survival-part-score"><p>%s</p></section>',
                esc_html($message)
            );
        }

        ob_start();
        $title = (string) $attributes['title'];
        $canEditScores = Capabilities::canManageScores();
        $partId = $selectedPartId;
        include __DIR__ . '/../../templates/frontend-part-score.php';

        return (string) ob_get_clean();
    }
}