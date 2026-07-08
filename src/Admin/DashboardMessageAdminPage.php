<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\DashboardMessageService;
use BSO\Survival\Service\EventService;

class DashboardMessageAdminPage {
    private const CREATE_NONCE_ACTION = 'bso_survival_dashboard_message_create';
    private const CREATE_NONCE_FIELD = 'bso_survival_dashboard_message_create_nonce';
    private const TOGGLE_NONCE_ACTION = 'bso_survival_dashboard_message_toggle';
    private const TOGGLE_NONCE_FIELD = 'bso_survival_dashboard_message_toggle_nonce';

    /** @var EventService */
    private $events;

    /** @var DashboardMessageService */
    private $messages;

    public function __construct(EventService $events, DashboardMessageService $messages) {
        $this->events = $events;
        $this->messages = $messages;
    }

    public function registerMenu(): void {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'bso-survival-rules',
            __('Dashboard Meldingen', 'bso-survival'),
            __('Dashboard Meldingen', 'bso-survival'),
            'manage_options',
            'bso-survival-dashboard-messages',
            [$this, 'renderPage']
        );
    }

    public function handleCreate(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::CREATE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::CREATE_NONCE_FIELD], self::CREATE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        try {
            $this->messages->create([
                'event_id' => $eventId,
                'type' => isset($_POST['type']) ? sanitize_key(wp_unslash((string) $_POST['type'])) : 'info',
                'text' => isset($_POST['text']) ? sanitize_textarea_field(wp_unslash((string) $_POST['text'])) : '',
                'visibility' => isset($_POST['visibility']) ? sanitize_key(wp_unslash((string) $_POST['visibility'])) : 'intern',
                'status' => isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'actief',
                'scope' => isset($_POST['scope']) ? sanitize_key(wp_unslash((string) $_POST['scope'])) : 'event',
                'changed_by' => isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin',
            ]);

            $this->redirectWithStatus($eventId, 'created');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage());
        }
    }

    public function handleToggle(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::TOGGLE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::TOGGLE_NONCE_FIELD], self::TOGGLE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $messageId = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'inactief';

        try {
            $this->messages->setStatus(
                $messageId,
                $eventId,
                $status,
                isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin'
            );

            $this->redirectWithStatus($eventId, 'updated');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage());
        }
    }

    public function renderPage(): void {
        $this->assertAdminPermissions();

        $events = $this->events->listEvents();
        $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
        $scope = isset($_GET['scope']) ? sanitize_key(wp_unslash((string) $_GET['scope'])) : 'all';
        if (!in_array($scope, ['all', 'event', 'global'], true)) {
            $scope = 'all';
        }
        if ($eventId <= 0 && !empty($events)) {
            $eventId = (int) $events[0]->id;
        }

        $messages = $eventId > 0 ? $this->messages->listForEvent($eventId, 50, $scope) : [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Dashboard Meldingen', 'bso-survival') . '</h1>';

        if (isset($_GET['saved']) && $_GET['saved'] === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Melding opgeslagen.', 'bso-survival') . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Meldingstatus bijgewerkt.', 'bso-survival') . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'error') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : __('Onbekende fout.', 'bso-survival');
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-survival-dashboard-messages" />';
        echo '<label for="bso-msg-event-filter"><strong>' . esc_html__('Event', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-msg-event-filter" name="event_id">';
        foreach ($events as $event) {
            $selected = selected($eventId, (int) $event->id, false);
            echo '<option value="' . (int) $event->id . '" ' . $selected . '>' . esc_html((string) $event->name) . '</option>';
        }
        echo '</select> ';
        echo '<label for="bso-msg-scope-filter"><strong>' . esc_html__('Scope', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-msg-scope-filter" name="scope">';
        echo '<option value="all" ' . selected($scope, 'all', false) . '>' . esc_html__('event + global', 'bso-survival') . '</option>';
        echo '<option value="event" ' . selected($scope, 'event', false) . '>' . esc_html__('alleen event', 'bso-survival') . '</option>';
        echo '<option value="global" ' . selected($scope, 'global', false) . '>' . esc_html__('alleen global', 'bso-survival') . '</option>';
        echo '</select> ';
        echo '<button class="button button-secondary">' . esc_html__('Laden', 'bso-survival') . '</button>';
        echo '</form>';

        echo '<hr />';

        echo '<h2>' . esc_html__('Nieuwe melding', 'bso-survival') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_dashboard_message_create" />';
        echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
        wp_nonce_field(self::CREATE_NONCE_ACTION, self::CREATE_NONCE_FIELD);

        echo '<p><label for="bso-msg-type"><strong>' . esc_html__('Type', 'bso-survival') . '</strong></label><br />';
        echo '<select id="bso-msg-type" name="type">';
        echo '<option value="info">info</option>';
        echo '<option value="warning">warning</option>';
        echo '<option value="success">success</option>';
        echo '<option value="urgent">urgent</option>';
        echo '</select></p>';

        echo '<p><label for="bso-msg-text"><strong>' . esc_html__('Meldingtekst', 'bso-survival') . '</strong></label><br />';
        echo '<textarea id="bso-msg-text" name="text" rows="3" class="large-text" required="required"></textarea></p>';

        echo '<p><label for="bso-msg-visibility"><strong>' . esc_html__('Zichtbaarheid', 'bso-survival') . '</strong></label><br />';
        echo '<select id="bso-msg-visibility" name="visibility">';
        echo '<option value="intern">intern</option>';
        echo '<option value="publiek">publiek</option>';
        echo '</select></p>';

        echo '<p><label for="bso-msg-status"><strong>' . esc_html__('Status', 'bso-survival') . '</strong></label><br />';
        echo '<select id="bso-msg-status" name="status">';
        echo '<option value="actief">actief</option>';
        echo '<option value="inactief">inactief</option>';
        echo '</select></p>';

        echo '<p><label for="bso-msg-scope"><strong>' . esc_html__('Scope', 'bso-survival') . '</strong></label><br />';
        echo '<select id="bso-msg-scope" name="scope">';
        echo '<option value="event">event-specifiek</option>';
        echo '<option value="global">global</option>';
        echo '</select></p>';

        echo '<p><label for="bso-msg-by"><strong>' . esc_html__('Gewijzigd door', 'bso-survival') . '</strong></label><br />';
        echo '<input id="bso-msg-by" type="text" name="changed_by" value="admin" /></p>';

        echo '<p><button class="button button-primary">' . esc_html__('Melding opslaan', 'bso-survival') . '</button></p>';
        echo '</form>';

        echo '<hr />';

        echo '<h2>' . esc_html__('Bestaande meldingen', 'bso-survival') . '</h2>';
        if ($messages === []) {
            echo '<p>' . esc_html__('Nog geen meldingen voor dit event.', 'bso-survival') . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('ID', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Type', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Prioriteit', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Scope', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Tekst', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Status', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Actie', 'bso-survival') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($messages as $row) {
            $currentStatus = (string) ($row->status ?? 'inactief');
            $nextStatus = $currentStatus === 'actief' ? 'inactief' : 'actief';
            $type = (string) ($row->type ?? 'info');
            $priority = $this->priorityForType($type);
            $rowScope = (string) ($row->visibility ?? '') === 'global' ? 'global' : 'event';

            echo '<tr>';
            echo '<td>' . (int) ($row->id ?? 0) . '</td>';
            echo '<td>' . esc_html($type) . '</td>';
            echo '<td>' . (int) $priority . '</td>';
            echo '<td>' . esc_html($rowScope) . '</td>';
            echo '<td>' . esc_html((string) ($row->text ?? '')) . '</td>';
            echo '<td>' . esc_html($currentStatus) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="bso_survival_dashboard_message_toggle" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
            echo '<input type="hidden" name="message_id" value="' . (int) ($row->id ?? 0) . '" />';
            echo '<input type="hidden" name="status" value="' . esc_attr($nextStatus) . '" />';
            echo '<input type="hidden" name="changed_by" value="admin" />';
            wp_nonce_field(self::TOGGLE_NONCE_ACTION, self::TOGGLE_NONCE_FIELD);
            echo '<button class="button button-small">' . esc_html($nextStatus === 'actief' ? __('Activeren', 'bso-survival') : __('Deactiveren', 'bso-survival')) . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function assertAdminPermissions(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }
    }

    private function redirectWithStatus(int $eventId, string $saved, string $message = ''): void {
        $args = [
            'page' => 'bso-survival-dashboard-messages',
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

    private function priorityForType(string $type): int {
        switch ($type) {
            case 'urgent':
                return 400;
            case 'warning':
                return 300;
            case 'info':
                return 200;
            case 'success':
                return 100;
            default:
                return 50;
        }
    }
}
