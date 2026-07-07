<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartService;
use InvalidArgumentException;
use Throwable;

class PartsController {
    public const DEFAULT_EVENT_ID = 1;

    /** @var EventService */
    private $eventService;

    /** @var PartService */
    private $partService;

    public function __construct(EventService $eventService, PartService $partService) {
        $this->eventService = $eventService;
        $this->partService = $partService;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render(array $atts = []): string {
        wp_enqueue_style('bso-survival-frontend');
        wp_enqueue_script('bso-survival-frontend');

        $attributes = shortcode_atts([
            'title' => __('BSO Survival Onderdelen', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
        ], $atts, 'bso_survival_parts');

        $eventId = (int) $attributes['event_id'];

        try {
            $event = $this->eventService->getEvent($eventId);
            if ($event === null) {
                throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
            }

            $parts = $this->partService->listPartsForEvent($eventId);
        } catch (Throwable $exception) {
            $message = sprintf(__('Onderdelenlijst niet beschikbaar voor event_id %d.', 'bso-survival'), $eventId);

            if (function_exists('do_action')) {
                do_action('bso_survival_parts_render_error', $message, $eventId);
            }

            return sprintf(
                '<section class="bso-survival-parts"><p>%s</p></section>',
                esc_html($message)
            );
        }

        ob_start();
        $title = (string) $attributes['title'];
        include __DIR__ . '/../../templates/frontend-parts.php';

        return (string) ob_get_clean();
    }
}