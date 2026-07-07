<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\TeamService;
use InvalidArgumentException;
use Throwable;

class TeamsController {
    public const DEFAULT_EVENT_ID = 1;

    /** @var EventService */
    private $eventService;

    /** @var TeamService */
    private $teamService;

    public function __construct(EventService $eventService, TeamService $teamService) {
        $this->eventService = $eventService;
        $this->teamService = $teamService;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render(array $atts = []): string {
        wp_enqueue_style('bso-survival-frontend');
        wp_enqueue_script('bso-survival-frontend');

        $attributes = shortcode_atts([
            'title' => __('BSO Survival Teams', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
        ], $atts, 'bso_survival_teams');

        $eventId = (int) $attributes['event_id'];

        try {
            $event = $this->eventService->getEvent($eventId);
            if ($event === null) {
                throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
            }

            $teams = $this->teamService->listTeamsForEvent($eventId);
        } catch (Throwable $exception) {
            $message = sprintf(__('Teamlijst niet beschikbaar voor event_id %d.', 'bso-survival'), $eventId);

            if (function_exists('do_action')) {
                do_action('bso_survival_teams_render_error', $message, $eventId);
            }

            return sprintf(
                '<section class="bso-survival-teams"><p>%s</p></section>',
                esc_html($message)
            );
        }

        ob_start();
        $title = (string) $attributes['title'];
        include __DIR__ . '/../../templates/frontend-teams.php';

        return (string) ob_get_clean();
    }
}