<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Database\Repository\DashboardWidgetLayoutRepository;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\DashboardWidgetLayoutService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use Throwable;

class DashboardController {
    public const DEFAULT_EVENT_ID = 0;

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
        wp_enqueue_style('bso-survival-dashboard-widgets');
        wp_enqueue_script('bso-survival-dashboard-widgets');

        $attributes = shortcode_atts([
            'title' => __('BSO Survival Dashboard', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
        ], $atts, 'bso_survival_dashboard');

        $eventOptions = $this->overviewService->listUpcomingActiveEvents(5);
        $requestedEventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
        if ($requestedEventId <= 0) {
            $requestedEventId = (int) $attributes['event_id'];
        }

        $eventId = $requestedEventId > 0
            ? $requestedEventId
            : $this->overviewService->resolveDefaultDashboardEventId();

        try {
            if ($eventId <= 0) {
                throw new \InvalidArgumentException('Geen actief event beschikbaar voor dashboardweergave.');
            }

            $overview = $this->overviewService->getOverviewForEvent($eventId);
        } catch (Throwable $exception) {
            $message = $eventId > 0
                ? sprintf(__('Dashboard niet beschikbaar voor event_id %d.', 'bso-survival'), $eventId)
                : __('Dashboard niet beschikbaar: geen actief event gevonden.', 'bso-survival');

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
        $dashboardNavigation = isset($layout['navigation']) && is_array($layout['navigation']) ? $layout['navigation'] : [];
        $registrationPageId = isset($dashboardNavigation['registration_page_id']) ? (int) $dashboardNavigation['registration_page_id'] : 0;
        $registrationPageUrl = ($registrationPageId > 0 && function_exists('get_permalink')) ? (string) get_permalink($registrationPageId) : '';
        $mainWidgetIds = $layout['main'] ?? DashboardWidgetRegistry::getSectionWidgetIds('main');
        $mainWidgetWidths = isset($layout['widths']['main']) && is_array($layout['widths']['main']) ? $layout['widths']['main'] : [];
        $mainWidgetIds = array_values(array_filter($mainWidgetIds, 'is_string'));

        $resolvedOperationsWidgetIds = isset($layout['operations']) && is_array($layout['operations'])
            ? $layout['operations']
            : DashboardWidgetRegistry::getSectionWidgetIds('operations');
        $resolvedOperationsWidgetIds = array_values(array_filter($resolvedOperationsWidgetIds, 'is_string'));

        $operationsWidgetIds = !empty($overview['status']['is_read_only'])
            ? []
            : $resolvedOperationsWidgetIds;
        $operationsWidgetWidths = !empty($overview['status']['is_read_only'])
            ? []
            : (isset($layout['widths']['operations']) && is_array($layout['widths']['operations']) ? $layout['widths']['operations'] : []);

        $mainFilters = [
            'event_id' => $eventId,
            'widget_ids' => $mainWidgetIds,
            'widget_widths' => $mainWidgetWidths,
            'registration_page_url' => $registrationPageUrl,
        ];
        $operationsFilters = ['event_id' => $eventId, 'widget_ids' => $operationsWidgetIds, 'widget_widths' => $operationsWidgetWidths];

        $this->enqueueWidgetDependencies('main', $mainFilters);
        $this->enqueueWidgetDependencies('operations', $operationsFilters);

        $widgetsHtml = DashboardWidgetRegistry::renderSection('main', $overview, $mainFilters);
        $operationsWidgetsHtml = DashboardWidgetRegistry::renderSection('operations', $overview, $operationsFilters);

        ob_start();
        $title = (string) $attributes['title'];
        $selectedEventId = $eventId;
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
