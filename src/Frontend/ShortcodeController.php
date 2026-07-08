<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Database\Repository\EventRepository;
use BSO\Survival\Database\Repository\EventPublicationRepository;
use BSO\Survival\Database\Repository\PartRepository;
use BSO\Survival\Database\Repository\TeamRepository;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\EventPublicationService;
use BSO\Survival\Service\PartService;
use BSO\Survival\Service\TeamService;

class ShortcodeController {
    public const TAG = 'bso_survival_dashboard';
    public const PARTS_TAG = 'bso_survival_parts';
    public const TEAMS_TAG = 'bso_survival_teams';
    public const OVERVIEW_TAG = 'bso_survival_event_overview';
    public const SUMMARY_TAG = 'bso_survival_event_summary';
    public const TEAM_REGISTRATION_TAG = 'bso_survival_team_registration';

    /** @var DashboardController */
    private $dashboardController;

    /** @var PartsController */
    private $partsController;

    /** @var TeamsController */
    private $teamsController;

    /** @var EventOverviewController */
    private $eventOverviewController;

    /** @var EventSummaryController */
    private $eventSummaryController;

    /** @var TeamRegistrationController */
    private $teamRegistrationController;

    public function __construct(DashboardController $dashboardController = null, PartsController $partsController = null, TeamsController $teamsController = null, EventOverviewController $eventOverviewController = null, EventSummaryController $eventSummaryController = null, TeamRegistrationController $teamRegistrationController = null) {
        $this->dashboardController = $dashboardController ?? $this->buildDashboardController();
        $this->partsController = $partsController ?? $this->buildPartsController();
        $this->teamsController = $teamsController ?? $this->buildTeamsController();
        $this->eventOverviewController = $eventOverviewController ?? $this->buildEventOverviewController();
        $this->eventSummaryController = $eventSummaryController ?? $this->buildEventSummaryController();
        $this->teamRegistrationController = $teamRegistrationController ?? $this->buildTeamRegistrationController();
    }

    public function register(): void {
        add_shortcode(self::TAG, [$this, 'render']);
        add_shortcode(self::PARTS_TAG, [$this, 'render_parts']);
        add_shortcode(self::TEAMS_TAG, [$this, 'render_teams']);
        add_shortcode(self::OVERVIEW_TAG, [$this, 'render_event_overview']);
        add_shortcode(self::SUMMARY_TAG, [$this, 'render_event_summary']);
        add_shortcode(self::TEAM_REGISTRATION_TAG, [$this, 'render_team_registration']);
    }

    public function render(array $atts = []): string {
        return $this->dashboardController->render($atts);
    }

    public function render_parts(array $atts = []): string {
        return $this->partsController->render($atts);
    }

    public function render_teams(array $atts = []): string {
        return $this->teamsController->render($atts);
    }

    public function render_event_overview(array $atts = []): string {
        return $this->eventOverviewController->render($atts);
    }

    public function render_event_summary(array $atts = []): string {
        return $this->eventSummaryController->render($atts);
    }

    public function render_team_registration(array $atts = []): string {
        return $this->teamRegistrationController->render($atts);
    }

    private function buildDashboardController(): DashboardController {
        $eventRepository = new EventRepository();
        $partRepository = new PartRepository();
        $teamRepository = new TeamRepository();

        $eventService = new EventService($eventRepository);
        $partService = new PartService($partRepository);
        $teamService = new TeamService($teamRepository);
        $publicationService = new EventPublicationService(new EventPublicationRepository());

        return new DashboardController(
            new DashboardOverviewService($eventService, $partService, $teamService, $publicationService)
        );
    }

    private function buildPartsController(): PartsController {
        $eventRepository = new EventRepository();
        $partRepository = new PartRepository();

        $eventService = new EventService($eventRepository);
        $partService = new PartService($partRepository);

        return new PartsController($eventService, $partService);
    }

    private function buildTeamsController(): TeamsController {
        $eventRepository = new EventRepository();
        $teamRepository = new TeamRepository();

        $eventService = new EventService($eventRepository);
        $teamService = new TeamService($teamRepository);

        return new TeamsController($eventService, $teamService);
    }

    private function buildEventOverviewController(): EventOverviewController {
        $eventRepository = new EventRepository();
        $partRepository = new PartRepository();
        $teamRepository = new TeamRepository();

        $eventService = new EventService($eventRepository);
        $partService = new PartService($partRepository);
        $teamService = new TeamService($teamRepository);
        $publicationService = new EventPublicationService(new EventPublicationRepository());

        return new EventOverviewController(
            new DashboardOverviewService($eventService, $partService, $teamService, $publicationService)
        );
    }

    private function buildEventSummaryController(): EventSummaryController {
        $eventRepository = new EventRepository();
        $partRepository = new PartRepository();
        $teamRepository = new TeamRepository();

        $eventService = new EventService($eventRepository);
        $partService = new PartService($partRepository);
        $teamService = new TeamService($teamRepository);
        $publicationService = new EventPublicationService(new EventPublicationRepository());

        return new EventSummaryController(
            new DashboardOverviewService($eventService, $partService, $teamService, $publicationService)
        );
    }

    private function buildTeamRegistrationController(): TeamRegistrationController {
        $eventRepository = new EventRepository();
        $eventService = new EventService($eventRepository);

        return new TeamRegistrationController($eventService);
    }
}
