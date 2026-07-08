<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\EventAdminService;
use BSO\Survival\Service\EventService;

class EventAdminPage {
    private const CREATE_NONCE_ACTION = 'bso_survival_event_create';
    private const CREATE_NONCE_FIELD = 'bso_survival_event_create_nonce';
    private const UPDATE_NONCE_ACTION = 'bso_survival_event_update';
    private const UPDATE_NONCE_FIELD = 'bso_survival_event_update_nonce';
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

    public function handleUpdate(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::UPDATE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::UPDATE_NONCE_FIELD], self::UPDATE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        try {
            $updated = $this->admin->updateEvent(
                $eventId,
                isset($_POST['event_name']) ? sanitize_text_field(wp_unslash((string) $_POST['event_name'])) : '',
                isset($_POST['event_date']) ? sanitize_text_field(wp_unslash((string) $_POST['event_date'])) : '',
                isset($_POST['max_teams']) ? (int) $_POST['max_teams'] : 22
            );

            $this->redirectWithStatus((int) ($updated->id ?? $eventId), 'updated');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage());
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

        $status = $selectedEvent !== null ? (string) ($selectedEvent->status ?? '') : '';
        $isImmutable = in_array($status, ['afgesloten', 'gepubliceerd', 'verwijderd'], true);
        $partFilter = isset($_GET['part_filter']) ? sanitize_text_field(wp_unslash((string) $_GET['part_filter'])) : '';
        $allEligibleParts = $selectedEvent !== null ? $this->admin->listEligiblePartsForEvent($selectedEventId) : [];
        $parts = $selectedEvent !== null ? $this->admin->listEligiblePartsForEvent($selectedEventId, $partFilter) : [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Survival Events', 'bso-survival') . '</h1>';

        if (isset($_GET['saved']) && $_GET['saved'] === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Event aangemaakt.', 'bso-survival') . '</p></div>';
        }
        if (isset($_GET['saved']) && $_GET['saved'] === 'linked') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Parts gekoppeld aan event.', 'bso-survival') . '</p></div>';
        }
        if (isset($_GET['saved']) && $_GET['saved'] === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Event bijgewerkt.', 'bso-survival') . '</p></div>';
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
            foreach ($allEligibleParts as $part) {
                $partEventId = isset($part->event_id) ? (int) $part->event_id : 0;
                if ($partEventId === $selectedEventId) {
                    $attachedLookup[(int) ($part->id ?? 0)] = true;
                }
            }
            $visibleIds = [];
            foreach ($parts as $part) {
                $visibleIds[(int) ($part->id ?? 0)] = true;
            }

            if ($isImmutable) {
                echo '<div class="notice notice-warning inline"><p>' . esc_html__('Dit event is afgesloten/gepubliceerd/verwijderd en mag niet meer inhoudelijk aangepast worden.', 'bso-survival') . '</p></div>';
            }

            $maxTeams = 22;
            $meta = json_decode((string) ($selectedEvent->meta_data ?? ''), true);
            if (is_array($meta) && isset($meta['max_teams'])) {
                $maxTeams = (int) $meta['max_teams'];
            }

            echo '<h3>' . esc_html__('Eventgegevens bewerken', 'bso-survival') . '</h3>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:760px;">';
            echo '<input type="hidden" name="action" value="bso_survival_event_update" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $selectedEventId . '" />';
            wp_nonce_field(self::UPDATE_NONCE_ACTION, self::UPDATE_NONCE_FIELD);
            echo '<table class="form-table" role="presentation"><tbody>';
            echo '<tr><th scope="row"><label for="bso-event-edit-name">' . esc_html__('Naam', 'bso-survival') . '</label></th>';
            echo '<td><input id="bso-event-edit-name" name="event_name" type="text" class="regular-text" value="' . esc_attr((string) ($selectedEvent->name ?? '')) . '"' . ($isImmutable ? ' disabled="disabled"' : '') . ' /></td></tr>';
            echo '<tr><th scope="row"><label for="bso-event-edit-date">' . esc_html__('Datum', 'bso-survival') . '</label></th>';
            echo '<td><input id="bso-event-edit-date" name="event_date" type="date" value="' . esc_attr((string) ($selectedEvent->event_date ?? '')) . '"' . ($isImmutable ? ' disabled="disabled"' : '') . ' /></td></tr>';
            echo '<tr><th scope="row"><label for="bso-event-edit-max-teams">' . esc_html__('Max teams', 'bso-survival') . '</label></th>';
            echo '<td><input id="bso-event-edit-max-teams" name="max_teams" type="number" min="1" value="' . (int) $maxTeams . '"' . ($isImmutable ? ' disabled="disabled"' : '') . ' /></td></tr>';
            echo '</tbody></table>';
            echo '<p><button class="button button-secondary"' . ($isImmutable ? ' disabled="disabled"' : '') . '>' . esc_html__('Event opslaan', 'bso-survival') . '</button></p>';
            echo '</form>';

            echo '<h3>' . esc_html__('Parts koppelen aan dit event', 'bso-survival') . '</h3>';
            echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="margin:12px 0;max-width:980px;">';
            echo '<input type="hidden" name="page" value="bso-survival-events" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $selectedEventId . '" />';
            echo '<label for="bso-survival-part-filter"><strong>' . esc_html__('Filter onderdelen', 'bso-survival') . ':</strong></label> ';
            echo '<input id="bso-survival-part-filter" type="search" name="part_filter" value="' . esc_attr($partFilter) . '" class="regular-text" placeholder="' . esc_attr__('Zoek op naam', 'bso-survival') . '" /> ';
            echo '<button class="button">' . esc_html__('Filter', 'bso-survival') . '</button> ';
            echo '<a class="button button-link" href="' . esc_url(add_query_arg(['page' => 'bso-survival-events', 'event_id' => $selectedEventId], admin_url('admin.php'))) . '">' . esc_html__('Reset', 'bso-survival') . '</a>';
            echo '</form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="bso_survival_event_link_parts" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $selectedEventId . '" />';
            wp_nonce_field(self::LINK_PARTS_NONCE_ACTION, self::LINK_PARTS_NONCE_FIELD);
            foreach (array_keys($attachedLookup) as $attachedPartId) {
                if (isset($visibleIds[$attachedPartId])) {
                    continue;
                }

                echo '<input type="hidden" name="part_ids[]" value="' . (int) $attachedPartId . '" />';
            }

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

            if ($parts === []) {
                echo '<tr><td colspan="3">' . esc_html__('Geen geldige onderdelen gevonden voor dit event en huidige filter.', 'bso-survival') . '</td></tr>';
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
