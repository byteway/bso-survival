<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use Throwable;

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

        $eventId = (int) $attributes['event_id'];

        try {
            $overview = $this->overviewService->getOverviewForEvent($eventId);
        } catch (Throwable $exception) {
            $message = sprintf(__('Dashboard niet beschikbaar voor event_id %d.', 'bso-survival'), $eventId);

            if (function_exists('do_action')) {
                do_action('bso_survival_dashboard_render_error', $message, $eventId);
            }

            return sprintf(
                '<section class="bso-survival-dashboard"><p>%s</p></section>',
                esc_html($message)
            );
        }

        if (DashboardWidgetRegistry::getSection('main') === []) {
            DashboardWidgetRegistry::initDefaults();
        }

        $this->enqueueWidgetDependencies('main');
        $this->enqueueWidgetDependencies('operations');

        $widgetsHtml = DashboardWidgetRegistry::renderSection('main', $overview, ['event_id' => $eventId]);
        $operationsWidgetsHtml = DashboardWidgetRegistry::renderSection('operations', $overview, ['event_id' => $eventId]);

        ob_start();
        $title = (string) $attributes['title'];
        include __DIR__ . '/../../templates/frontend-dashboard.php';

        return (string) ob_get_clean();
    }

    private function enqueueWidgetDependencies(string $section): void {
        foreach (DashboardWidgetRegistry::getSectionStyleDependencies($section) as $styleHandle) {
            wp_enqueue_style($styleHandle);
        }

        foreach (DashboardWidgetRegistry::getSectionScriptDependencies($section) as $scriptHandle) {
            wp_enqueue_script($scriptHandle);
        }
    }
}
