<?php

namespace BSO\Survival\Frontend;

use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\EventService;
use InvalidArgumentException;
use Throwable;

class ScoreFormController {
    public const DEFAULT_EVENT_ID = 1;

    /** @var EventService */
    private $events;

    /** @var DashboardOverviewService */
    private $overview;

    /** @var AssignmentRepositoryInterface */
    private $assignments;

    public function __construct(EventService $events, DashboardOverviewService $overview, AssignmentRepositoryInterface $assignments) {
        $this->events = $events;
        $this->overview = $overview;
        $this->assignments = $assignments;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render(array $atts = []): string {
        wp_enqueue_style('bso-survival-frontend');
        wp_enqueue_script('bso-survival-frontend-score');

        $attributes = shortcode_atts([
            'title' => __('Score-invoer', 'bso-survival'),
            'event_id' => self::DEFAULT_EVENT_ID,
            'button_label' => __('Score opslaan', 'bso-survival'),
        ], $atts, 'bso_survival_score_form');

        $eventId = (int) $attributes['event_id'];

        try {
            $event = $this->events->getEvent($eventId);
            if ($event === null) {
                throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
            }

            $overview = $this->overview->getOverviewForEvent($eventId);
            $assignments = $this->assignments->findByEventId($eventId);
        } catch (Throwable $exception) {
            $message = sprintf(__('Scoreformulier niet beschikbaar voor event_id %d.', 'bso-survival'), $eventId);

            if (function_exists('do_action')) {
                do_action('bso_survival_score_form_render_error', $message, $eventId);
            }

            return sprintf(
                '<section class="bso-survival-score-form"><p>%s</p></section>',
                esc_html($message)
            );
        }

        $isReadOnly = !empty($overview['status']['is_read_only']) || !empty($overview['status']['is_published']);
        $restUrl = function_exists('rest_url')
            ? (string) rest_url('bso-survival/v1/score-entries')
            : '';
        $nonce = function_exists('wp_create_nonce')
            ? (string) wp_create_nonce('bso_survival_score_submission')
            : '';
        $partStatusRestBase = function_exists('rest_url')
            ? (string) rest_url('bso-survival/v2/scores/parts')
            : '';
        $restNonce = function_exists('wp_create_nonce')
            ? (string) wp_create_nonce('wp_rest')
            : '';

        ob_start();
        $title = (string) $attributes['title'];
        $buttonLabel = (string) $attributes['button_label'];
        include __DIR__ . '/../../templates/frontend-score-form.php';

        return (string) ob_get_clean();
    }
}
