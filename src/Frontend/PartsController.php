<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartHelpService;
use BSO\Survival\Service\PartService;
use InvalidArgumentException;
use Throwable;

class PartsController {
    public const DEFAULT_EVENT_ID = 1;

    /** @var EventService */
    private $eventService;

    /** @var PartService */
    private $partService;

    /** @var PartHelpService */
    private $partHelpService;

    public function __construct(EventService $eventService, PartService $partService, PartHelpService $partHelpService) {
        $this->eventService = $eventService;
        $this->partService = $partService;
        $this->partHelpService = $partHelpService;
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

        $selectedPartId = isset($_GET['part_id']) ? (int) $_GET['part_id'] : 0;
        $selectedPart = $this->resolveSelectedPart($parts, $selectedPartId);
        if ($selectedPart === null) {
            $message = __('Geen onderdelen beschikbaar voor de helpweergave.', 'bso-survival');
            return sprintf('<section class="bso-survival-parts"><p>%s</p></section>', esc_html($message));
        }

        $selectedPartId = (int) ($selectedPart->id ?? 0);
        $selectedHelp = $this->partHelpService->renderForPart($selectedPart);
        $links = $this->buildPartNavigation($parts, $selectedPartId);

        ob_start();
        $title = (string) $attributes['title'];
        include __DIR__ . '/../../templates/frontend-parts.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<int, object> $parts
     * @return object|null
     */
    private function resolveSelectedPart(array $parts, int $selectedPartId) {
        if ($parts === []) {
            return null;
        }

        if ($selectedPartId > 0) {
            foreach ($parts as $part) {
                if ((int) ($part->id ?? 0) === $selectedPartId) {
                    return $part;
                }
            }
        }

        return $parts[0] ?? null;
    }

    /**
     * @param array<int, object> $parts
     * @return array{prev:object|null,next:object|null}
     */
    private function buildPartNavigation(array $parts, int $selectedPartId): array {
        $selectedIndex = 0;
        foreach ($parts as $index => $part) {
            if ((int) ($part->id ?? 0) === $selectedPartId) {
                $selectedIndex = (int) $index;
                break;
            }
        }

        $prev = $selectedIndex > 0 ? $parts[$selectedIndex - 1] : null;
        $next = ($selectedIndex + 1) < count($parts) ? $parts[$selectedIndex + 1] : null;

        return [
            'prev' => $prev,
            'next' => $next,
        ];
    }
}