<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\DashboardOverviewService;

class DashboardController {
    public const DEFAULT_EVENT_ID = 1;

    /** @var DashboardOverviewService */
    private $overviewService;

    public function __construct(DashboardOverviewService $overviewService) {
        $this->overviewService = $overviewService;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render(array $atts = []): string {
        wp_enqueue_style('bso-survival-frontend');
        wp_enqueue_script('bso-survival-frontend');

        $attributes = shortcode_atts([
            'title' => __('BSO Survival Dashboard', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
        ], $atts, 'bso_survival_dashboard');

        $overview = $this->overviewService->getOverviewForEvent((int) $attributes['event_id']);

        ob_start();
        $title = (string) $attributes['title'];
        include __DIR__ . '/../../templates/frontend-dashboard.php';

        return (string) ob_get_clean();
    }
}
