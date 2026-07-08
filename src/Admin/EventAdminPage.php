<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\EventAdminService;
use BSO\Survival\Service\EventService;

class EventAdminPage {
    private const CREATE_NONCE_ACTION = 'bso_survival_event_create';
    private const CREATE_NONCE_FIELD = 'bso_survival_event_create_nonce';
    private const LINK_PARTS_NONCE_ACTION = 'bso_survival_event_link_parts';
    private const LINK_PARTS_NONCE_FIELD = 'bso_survival_event_link_parts_nonce';
    private const DELETE_NONCE_ACTION = 'bso_survival_event_delete';
    private const DELETE_NONCE_FIELD = 'bso_survival_event_delete_nonce';

    /** @var EventService */
    private $events;

    /** @var EventAdminService */
    private $admin;

    public function __construct(EventService $events, EventAdminService $admin) {
        $this->events = $events;
        $this->admin = $admin;
    }

    public function registerMenu(): void {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'bso-survival-rules',
            __('Events', 'bso-survival'),
            __('Events', 'bso-survival'),
            'manage_options',
            'bso-survival-events',
            [$this, 'renderPage']
        );
    }

    public function handleCreate(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::CREATE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::CREATE_NONCE_FIELD], self::CREATE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        try {
            $created = $this->admin->createEvent(
                isset($_POST['event_name']) ? sanitize_text_field(wp_unslash((string) $_POST['event_name'])) : '',
                isset($_POST['event_date']) ? sanitize_text_field(wp_unslash((string) $_POST['event_date'])) : '',
                isset($_POST['max_teams']) ? (int) $_POST['max_teams'] : 22
            );

            $this->redirectWithStatus((int) ($created->id ?? 0), 'created');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus(0, 'error', $exception->getMessage());
        }
    }

    public function handleLinkParts(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::LINK_PARTS_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::LINK_PARTS_NONCE_FIELD], self::LINK_PARTS_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $partIds = isset($_POST['part_ids']) && is_array($_POST['part_ids']) ? array_values($_POST['part_ids']) : [];

        try {
            $this->admin->linkPartsToEvent($eventId, $partIds);
            $this->redirectWithStatus($eventId, 'linked');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage());
        }
    }

    public function handleDelete(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::DELETE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::DELETE_NONCE_FIELD], self::DELETE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        try {
            $this->admin->deleteEventFromAdmin($eventId);
            $this->redirectWithStatus(0, 'deleted');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage());
        }
    }

    public function renderPage(): void {
        $this->assertAdminPermissions();

        $events = $this->events->listEvents();
        $selectedEventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
        if ($selectedEventId <= 0 && $events !== []) {
            $selectedEventId = (int) ($events[0]->id ?? 0);
        }

        $selectedEvent = null;
        foreach ($events as $event) {
            if ((int) ($event->id ?? 0) === $selectedEventId) {
                $selectedEvent = $event;
                break;
            }
        }

        $parts = $this->admin->listLinkableParts();
        $status = $selectedEvent !== null ? (string) ($selectedEvent->status ?? '') : '';
        $isImmutable = in_array($status, ['afgesloten', 'gepubliceerd', 'verwijderd'], true);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Survival Events', 'bso-survival') . '</h1>';

        if (isset($_GET['saved']) && $_GET['saved'] === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Event aangemaakt.', 'bso-survival') . '</p></div>';
        }
        if (isset($_GET['saved']) && $_GET['saved'] === 'linked') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Parts gekoppeld aan event.', 'bso-survival') . '</p></div>';
        }
        if (isset($_GET['saved']) && $_GET['saved'] === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Event verwijderd uit actieve administratie. Samenvatting blijft behouden.', 'bso-survival') . '</p></div>';
        }
        if (isset($_GET['saved']) && $_GET['saved'] === 'error') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : __('Onbekende fout.', 'bso-survival');
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<h2>' . esc_html__('Nieuw event aanmaken', 'bso-survival') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_event_create" />';
        wp_nonce_field(self::CREATE_NONCE_ACTION, self::CREATE_NONCE_FIELD);

        echo '<table class="form-table" role="presentation" style="max-width:760px;"><tbody>';
        echo '<tr><th scope="row"><label for="bso-event-name">' . esc_html__('Naam', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-event-name" name="event_name" type="text" class="regular-text" required="required" /></td></tr>';

        echo '<tr><th scope="row"><label for="bso-event-date">' . esc_html__('Datum', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-event-date" name="event_date" type="date" required="required" /></td></tr>';

        echo '<tr><th scope="row"><label for="bso-max-teams">' . esc_html__('Max teams', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-max-teams" name="max_teams" type="number" min="1" value="22" /></td></tr>';
        echo '</tbody></table>';
        echo '<p><button class="button button-primary">' . esc_html__('Event aanmaken', 'bso-survival') . '</button></p>';
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Bestaand event beheren', 'bso-survival') . '</h2>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-survival-events" />';
        echo '<label for="bso-survival-events-filter"><strong>' . esc_html__('Event', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-survival-events-filter" name="event_id">';
        foreach ($events as $event) {
            $eventId = (int) ($event->id ?? 0);
            $selected = selected($selectedEventId, $eventId, false);
            $label = sprintf('#%d %s (%s)', $eventId, (string) ($event->name ?? ''), (string) ($event->status ?? 'onbekend'));
            echo '<option value="' . $eventId . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button">' . esc_html__('Laden', 'bso-survival') . '</button>';
        echo '</form>';

        if ($selectedEvent !== null) {
            $selectedEventId = (int) ($selectedEvent->id ?? 0);
            $attachedLookup = [];
            foreach ($parts as $part) {
                $partEventId = isset($part->event_id) ? (int) $part->event_id : 0;
                if ($partEventId === $selectedEventId) {
                    $attachedLookup[(int) ($part->id ?? 0)] = true;
                }
            }

            if ($isImmutable) {
                echo '<div class="notice notice-warning inline"><p>' . esc_html__('Dit event is afgesloten/gepubliceerd/verwijderd en mag niet meer inhoudelijk aangepast worden.', 'bso-survival') . '</p></div>';
            }

            echo '<h3>' . esc_html__('Parts koppelen aan dit event', 'bso-survival') . '</h3>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="bso_survival_event_link_parts" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $selectedEventId . '" />';
            wp_nonce_field(self::LINK_PARTS_NONCE_ACTION, self::LINK_PARTS_NONCE_FIELD);

            echo '<table class="widefat striped" style="max-width:980px;">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Koppelen', 'bso-survival') . '</th>';
            echo '<th>' . esc_html__('Part', 'bso-survival') . '</th>';
            echo '<th>' . esc_html__('Huidig event', 'bso-survival') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($parts as $part) {
                $partId = (int) ($part->id ?? 0);
                $ownerEventId = isset($part->event_id) ? (int) $part->event_id : 0;
                $ownerLabel = $ownerEventId > 0 ? ('#' . $ownerEventId) : __('niet gekoppeld', 'bso-survival');

                $checked = checked(isset($attachedLookup[$partId]), true, false);
                $disabled = $isImmutable ? ' disabled="disabled"' : '';

                echo '<tr>';
                echo '<td><input type="checkbox" name="part_ids[]" value="' . $partId . '" ' . $checked . $disabled . ' /></td>';
                echo '<td>' . esc_html((string) ($part->name ?? ('Part #' . $partId))) . '</td>';
                echo '<td>' . esc_html((string) $ownerLabel) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<p><button class="button button-secondary"' . ($isImmutable ? ' disabled="disabled"' : '') . '>' . esc_html__('Part-koppelingen opslaan', 'bso-survival') . '</button></p>';
            echo '</form>';

            echo '<h3>' . esc_html__('Event verwijderen', 'bso-survival') . '</h3>';
            echo '<p class="description">' . esc_html__('Verwijderen markeert het event als verwijderd in de admin. Gekoppelde parts blijven bestaan en worden losgekoppeld.', 'bso-survival') . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Weet je zeker dat je dit event wilt verwijderen uit de actieve administratie?', 'bso-survival')) . '\');">';
            echo '<input type="hidden" name="action" value="bso_survival_event_delete" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $selectedEventId . '" />';
            wp_nonce_field(self::DELETE_NONCE_ACTION, self::DELETE_NONCE_FIELD);
            echo '<p><button class="button button-link-delete">' . esc_html__('Event verwijderen', 'bso-survival') . '</button></p>';
            echo '</form>';
        }

        echo '</div>';
    }

    private function assertAdminPermissions(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }
    }

    private function redirectWithStatus(int $eventId, string $saved, string $message = ''): void {
        $args = [
            'page' => 'bso-survival-events',
            'saved' => $saved,
        ];

        if ($eventId > 0) {
            $args['event_id'] = $eventId;
        }

        if ($message !== '') {
            $args['message'] = $message;
        }

        $redirect = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
}
