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
        wp_enqueue_script('bso-survival-frontend-score');

        $attributes = shortcode_atts([
            'title' => __('Team Score', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
            'team_id' => 0,
        ], $atts, 'bso_survival_team_score');

        $eventId = (int) $attributes['event_id'];
        $selectedTeamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : (int) $attributes['team_id'];

        try {
            $event = $this->eventService->getEvent($eventId);
            if ($event === null) {
                throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
            }

            $teams = $this->teamService->listTeamsForEvent($eventId);
            if ($teams === []) {
                throw new InvalidArgumentException(sprintf('Geen teams gevonden voor event %d.', $eventId));
            }

            if ($selectedTeamId <= 0) {
                $selectedTeamId = (int) ($teams[0]->id ?? 0);
            }

            $team = null;
            foreach ($teams as $teamOption) {
                if ((int) ($teamOption->id ?? 0) === $selectedTeamId) {
                    $team = $teamOption;
                    break;
                }
            }

            if ($team === null) {
                $selectedTeamId = (int) ($teams[0]->id ?? 0);
                $team = $teams[0] ?? null;
            }

            if ($team === null) {
                throw new InvalidArgumentException(sprintf('Team %d hoort niet bij event %d.', $selectedTeamId, $eventId));
            }

            $overview = $this->scores->getTeamOverview($eventId, $selectedTeamId);
        } catch (Throwable $exception) {
            $message = sprintf(__('Teamscore niet beschikbaar voor event_id %d en team_id %d.', 'bso-survival'), $eventId, $selectedTeamId);

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
        $teamId = $selectedTeamId;
        include __DIR__ . '/../../templates/frontend-team-score.php';

        return (string) ob_get_clean();
    }
}