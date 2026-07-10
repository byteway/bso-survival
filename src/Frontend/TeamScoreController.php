<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\InterimTeamScoreService;
use BSO\Survival\Service\TeamService;
use BSO\Survival\Support\Capabilities;
use InvalidArgumentException;
use Throwable;

class TeamScoreController {
    public const DEFAULT_EVENT_ID = 1;

    /** @var EventService */
    private $eventService;

    /** @var TeamService */
    private $teamService;

    /** @var InterimTeamScoreService */
    private $scores;

    public function __construct(EventService $eventService, TeamService $teamService, InterimTeamScoreService $scores) {
        $this->eventService = $eventService;
        $this->teamService = $teamService;
        $this->scores = $scores;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render(array $atts = []): string {
        wp_enqueue_style('bso-survival-frontend');

        $attributes = shortcode_atts([
            'title' => __('Team Score', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
            'team_id' => 0,
        ], $atts, 'bso_survival_team_score');

        $eventId = (int) $attributes['event_id'];
        $teamId = (int) $attributes['team_id'];

        try {
            if ($teamId <= 0) {
                throw new InvalidArgumentException('team_id is verplicht voor bso_survival_team_score.');
            }

            $event = $this->eventService->getEvent($eventId);
            if ($event === null) {
                throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
            }

            $team = $this->teamService->getTeam($teamId);
            if ($team === null || (int) ($team->event_id ?? 0) !== $eventId) {
                throw new InvalidArgumentException(sprintf('Team %d hoort niet bij event %d.', $teamId, $eventId));
            }

            $overview = $this->scores->getTeamOverview($eventId, $teamId);
        } catch (Throwable $exception) {
            $message = sprintf(__('Teamscore niet beschikbaar voor event_id %d en team_id %d.', 'bso-survival'), $eventId, $teamId);

            if (function_exists('do_action')) {
                do_action('bso_survival_teams_render_error', $message, $eventId);
            }

            return sprintf(
                '<section class="bso-survival-team-score"><p>%s</p></section>',
                esc_html($message)
            );
        }

        ob_start();
        $title = (string) $attributes['title'];
        $canEditScores = Capabilities::canManageScores();
        include __DIR__ . '/../../templates/frontend-team-score.php';

        return (string) ob_get_clean();
    }
}