<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\DashboardOverviewService;
use Throwable;

class EventOverviewController {
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
            'title' => __('BSO Survival Eventoverzicht', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
            'compact' => 'no',
        ], $atts, 'bso_survival_event_overview');

        $eventId = (int) $attributes['event_id'];

        try {
            $overview = $this->overviewService->getOverviewForEvent($eventId);
        } catch (Throwable $exception) {
            $message = sprintf(__('Eventoverzicht niet beschikbaar voor event_id %d.', 'bso-survival'), $eventId);

            if (function_exists('do_action')) {
                do_action('bso_survival_event_overview_render_error', $message, $eventId);
            }

            return sprintf(
                '<section class="bso-survival-overview"><p>%s</p></section>',
                esc_html($message)
            );
        }

        ob_start();
        $title = (string) $attributes['title'];
        $compact = strtolower((string) $attributes['compact']) === 'yes';
        include __DIR__ . '/../../templates/frontend-event-overview.php';

        return (string) ob_get_clean();
    }
}
