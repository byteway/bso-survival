<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use BSO\Survival\Service\AdminScoreService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Support\Capabilities;

class ScoreEntryAdminPage {
    private const SAVE_NONCE_ACTION = 'bso_survival_admin_score_save';
    private const SAVE_NONCE_FIELD = 'bso_survival_admin_score_save_nonce';
    private const UPDATE_NONCE_ACTION = 'bso_survival_admin_score_update';
    private const UPDATE_NONCE_FIELD = 'bso_survival_admin_score_update_nonce';

    /** @var EventService */
    private $events;

    /** @var AssignmentRepositoryInterface */
    private $assignments;

    /** @var AdminScoreService */
    private $scores;

    public function __construct(EventService $events, AssignmentRepositoryInterface $assignments, AdminScoreService $scores) {
        $this->events = $events;
        $this->assignments = $assignments;
        $this->scores = $scores;
    }

    public function registerMenu(): void {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'bso-survival-rules',
            __('Score Invoer', 'bso-survival'),
            __('Score Invoer', 'bso-survival'),
            Capabilities::MANAGE_SCORES,
            'bso-survival-score-entry',
            [$this, 'renderPage']
        );
    }

    public function handleCreate(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::SAVE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::SAVE_NONCE_FIELD], self::SAVE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        try {
            $this->scores->create([
                'event_id' => $eventId,
                'assignment_id' => isset($_POST['assignment_id']) ? (int) $_POST['assignment_id'] : 0,
                'raw_value' => isset($_POST['raw_value']) ? (string) wp_unslash((string) $_POST['raw_value']) : '',
                'changed_by' => isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin',
                'entered_by_role' => 'admin',
            ]);

            $this->redirectWithStatus($eventId, 'created');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage());
        }
    }

    public function handleUpdate(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::UPDATE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::UPDATE_NONCE_FIELD], self::UPDATE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $scoreEntryId = isset($_POST['score_entry_id']) ? (int) $_POST['score_entry_id'] : 0;

        try {
            $this->scores->update($scoreEntryId, [
                'event_id' => $eventId,
                'raw_value' => isset($_POST['raw_value']) ? (string) wp_unslash((string) $_POST['raw_value']) : '',
                'changed_by' => isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin',
                'entered_by_role' => 'admin',
            ]);

            $this->redirectWithStatus($eventId, 'updated');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage());
        }
    }

    public function renderPage(): void {
        $this->assertAdminPermissions();

        $events = $this->events->listEvents();
        $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
        if ($eventId <= 0 && !empty($events)) {
            $eventId = (int) $events[0]->id;
        }

        $assignments = $eventId > 0 ? $this->assignments->findByEventId($eventId) : [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Score Invoer', 'bso-survival') . '</h1>';

        if (isset($_GET['saved']) && $_GET['saved'] === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Score succesvol opgeslagen.', 'bso-survival') . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Score succesvol bijgewerkt.', 'bso-survival') . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'error') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : __('Onbekende fout.', 'bso-survival');
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-survival-score-entry" />';
        echo '<label for="bso-score-event-filter"><strong>' . esc_html__('Event', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-score-event-filter" name="event_id">';
        foreach ($events as $event) {
            $selected = selected($eventId, (int) $event->id, false);
            echo '<option value="' . (int) $event->id . '" ' . $selected . '>' . esc_html((string) $event->name) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button button-secondary">' . esc_html__('Laden', 'bso-survival') . '</button>';
        echo '</form>';

        echo '<hr />';

        echo '<h2>' . esc_html__('Nieuwe score opslaan', 'bso-survival') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_admin_score_create" />';
        echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

        echo '<p><label for="bso-score-assignment"><strong>' . esc_html__('Assignment', 'bso-survival') . '</strong></label><br />';
        echo '<select id="bso-score-assignment" name="assignment_id" required="required">';
        echo '<option value="">' . esc_html__('Kies assignment', 'bso-survival') . '</option>';
        foreach ($assignments as $assignment) {
            $label = sprintf('%s - %s (#%d)', (string) ($assignment->team_name ?? ''), (string) ($assignment->part_name ?? ''), (int) ($assignment->id ?? 0));
            echo '<option value="' . (int) ($assignment->id ?? 0) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="bso-score-raw-value"><strong>' . esc_html__('Ruwe score', 'bso-survival') . '</strong></label><br />';
        echo '<input id="bso-score-raw-value" type="number" step="0.01" name="raw_value" required="required" /></p>';

        echo '<p><label for="bso-score-changed-by"><strong>' . esc_html__('Gewijzigd door', 'bso-survival') . '</strong></label><br />';
        echo '<input id="bso-score-changed-by" type="text" name="changed_by" value="admin" /></p>';

        echo '<p><button class="button button-primary">' . esc_html__('Score opslaan', 'bso-survival') . '</button></p>';
        echo '</form>';

        echo '<hr />';

        echo '<h2>' . esc_html__('Bestaande score bijwerken', 'bso-survival') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_admin_score_update" />';
        echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
        wp_nonce_field(self::UPDATE_NONCE_ACTION, self::UPDATE_NONCE_FIELD);

        echo '<p><label for="bso-score-entry-id"><strong>' . esc_html__('Score entry ID', 'bso-survival') . '</strong></label><br />';
        echo '<input id="bso-score-entry-id" type="number" min="1" name="score_entry_id" required="required" /></p>';

        echo '<p><label for="bso-score-raw-value-update"><strong>' . esc_html__('Nieuwe ruwe score', 'bso-survival') . '</strong></label><br />';
        echo '<input id="bso-score-raw-value-update" type="number" step="0.01" name="raw_value" required="required" /></p>';

        echo '<p><label for="bso-score-changed-by-update"><strong>' . esc_html__('Gewijzigd door', 'bso-survival') . '</strong></label><br />';
        echo '<input id="bso-score-changed-by-update" type="text" name="changed_by" value="admin" /></p>';

        echo '<p><button class="button button-secondary">' . esc_html__('Score bijwerken', 'bso-survival') . '</button></p>';
        echo '</form>';

        echo '</div>';
    }

    private function assertAdminPermissions(): void {
        if (!Capabilities::canManageScores()) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }
    }

    private function redirectWithStatus(int $eventId, string $saved, string $message = ''): void {
        $args = [
            'page' => 'bso-survival-score-entry',
            'event_id' => $eventId,
            'saved' => $saved,
        ];

        if ($message !== '') {
            $args['message'] = $message;
        }

        $redirect = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
}
