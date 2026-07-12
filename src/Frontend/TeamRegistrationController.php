<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Service\EventService;
use InvalidArgumentException;
use Throwable;

class TeamRegistrationController {
    public const DEFAULT_EVENT_ID = 1;

    /** @var EventService */
    private $events;

    public function __construct(EventService $events) {
        $this->events = $events;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render(array $atts = []): string {
        wp_enqueue_style('bso-survival-frontend');
        wp_enqueue_script('bso-survival-team-registration');

        $attributes = shortcode_atts([
            'title' => __('Team inschrijving', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
            'button_label' => __('Team inschrijven', 'bso-survival'),
        ], $atts, 'bso_survival_team_registration');

        $eventId = (int) $attributes['event_id'];

        try {
            $event = $this->events->getEvent($eventId);
            if ($event === null) {
                throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
            }
        } catch (Throwable $exception) {
            $message = sprintf(__('Inschrijfformulier niet beschikbaar voor event_id %d.', 'bso-survival'), $eventId);

            return sprintf(
                '<section class="bso-survival-registration"><p>%s</p></section>',
                esc_html($message)
            );
        }

        $restUrl = function_exists('rest_url')
            ? (string) rest_url('bso-survival/v1/registrations')
            : '';
        $restNonce = function_exists('wp_create_nonce')
            ? (string) wp_create_nonce('wp_rest')
            : '';
        $nonce = function_exists('wp_create_nonce')
            ? (string) wp_create_nonce('bso_survival_registration')
            : '';

        ob_start();
        $title = (string) $attributes['title'];
        $buttonLabel = (string) $attributes['button_label'];
        include __DIR__ . '/../../templates/frontend-team-registration.php';

        return (string) ob_get_clean();
    }
}
