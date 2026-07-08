<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Database\Repository\DashboardWidgetLayoutRepository;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\DashboardWidgetLayoutService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use Throwable;

class DashboardController {
    public const DEFAULT_EVENT_ID = 1;

    /** @var DashboardOverviewService */
    private $overviewService;

    /** @var DashboardWidgetLayoutService */
    private $layoutService;

    public function __construct(DashboardOverviewService $overviewService, DashboardWidgetLayoutService $layoutService = null) {
        $this->overviewService = $overviewService;
        $this->layoutService = $layoutService ?? new DashboardWidgetLayoutService(new DashboardWidgetLayoutRepository());
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

        $layout = $this->layoutService->getLayoutForEvent($eventId);
        $mainWidgetIds = $layout['main'] ?? DashboardWidgetRegistry::getSectionWidgetIds('main');
        $operationsWidgetIds = !empty($overview['status']['is_read_only'])
            ? []
            : ($layout['operations'] ?? DashboardWidgetRegistry::getSectionWidgetIds('operations'));

        $mainFilters = ['event_id' => $eventId, 'widget_ids' => $mainWidgetIds];
        $operationsFilters = ['event_id' => $eventId, 'widget_ids' => $operationsWidgetIds];

        $this->enqueueWidgetDependencies('main', $mainFilters);
        $this->enqueueWidgetDependencies('operations', $operationsFilters);

        $widgetsHtml = DashboardWidgetRegistry::renderSection('main', $overview, $mainFilters);
        $operationsWidgetsHtml = DashboardWidgetRegistry::renderSection('operations', $overview, $operationsFilters);

        ob_start();
        $title = (string) $attributes['title'];
        include __DIR__ . '/../../templates/frontend-dashboard.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function enqueueWidgetDependencies(string $section, array $filters = []): void {
        foreach (DashboardWidgetRegistry::getSectionStyleDependencies($section, $filters) as $styleHandle) {
            wp_enqueue_style($styleHandle);
        }

        foreach (DashboardWidgetRegistry::getSectionScriptDependencies($section, $filters) as $scriptHandle) {
            wp_enqueue_script($scriptHandle);
        }
    }
}
