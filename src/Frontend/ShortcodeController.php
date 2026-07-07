<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Database\Repository\EventRepository;
use BSO\Survival\Database\Repository\PartRepository;
use BSO\Survival\Database\Repository\TeamRepository;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartService;
use BSO\Survival\Service\TeamService;

class ShortcodeController {
    public const TAG = 'bso_survival_dashboard';

    /** @var DashboardController */
    private $dashboardController;

    public function __construct(DashboardController $dashboardController = null) {
        $this->dashboardController = $dashboardController ?? $this->buildDashboardController();
    }

    public function register(): void {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    public function render(array $atts = []): string {
        return $this->dashboardController->render($atts);
    }

    private function buildDashboardController(): DashboardController {
        $eventRepository = new EventRepository();
        $partRepository = new PartRepository();
        $teamRepository = new TeamRepository();

        $eventService = new EventService($eventRepository);
        $partService = new PartService($partRepository);
        $teamService = new TeamService($teamRepository);

        return new DashboardController(
            new DashboardOverviewService($eventService, $partService, $teamService)
        );
    }
}
