<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\DashboardMessageService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Support\Capabilities;

class DashboardMessageAdminPage {
    private const CREATE_NONCE_ACTION = 'bso_survival_dashboard_message_create';
    private const CREATE_NONCE_FIELD = 'bso_survival_dashboard_message_create_nonce';
    private const UPDATE_NONCE_ACTION = 'bso_survival_dashboard_message_update';
    private const UPDATE_NONCE_FIELD = 'bso_survival_dashboard_message_update_nonce';
    private const TOGGLE_NONCE_ACTION = 'bso_survival_dashboard_message_toggle';
    private const TOGGLE_NONCE_FIELD = 'bso_survival_dashboard_message_toggle_nonce';
    private const DELETE_NONCE_ACTION = 'bso_survival_dashboard_message_delete';
    private const DELETE_NONCE_FIELD = 'bso_survival_dashboard_message_delete_nonce';

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
            Capabilities::MANAGE_MESSAGES,
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
                'visible_from' => isset($_POST['visible_from']) ? sanitize_text_field(wp_unslash((string) $_POST['visible_from'])) : '',
                'visible_until' => isset($_POST['visible_until']) ? sanitize_text_field(wp_unslash((string) $_POST['visible_until'])) : '',
                'changed_by' => isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin',
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
        $messageId = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;

        try {
            $this->messages->update(
                $messageId,
                $eventId,
                [
                    'type' => isset($_POST['type']) ? sanitize_key(wp_unslash((string) $_POST['type'])) : '',
                    'text' => isset($_POST['text']) ? sanitize_textarea_field(wp_unslash((string) $_POST['text'])) : '',
                    'visibility' => isset($_POST['visibility']) ? sanitize_key(wp_unslash((string) $_POST['visibility'])) : '',
                    'status' => isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : '',
                    'scope' => isset($_POST['scope']) ? sanitize_key(wp_unslash((string) $_POST['scope'])) : '',
                    'visible_from' => isset($_POST['visible_from']) ? sanitize_text_field(wp_unslash((string) $_POST['visible_from'])) : '',
                    'visible_until' => isset($_POST['visible_until']) ? sanitize_text_field(wp_unslash((string) $_POST['visible_until'])) : '',
                ],
                isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin'
            );

            $this->redirectWithStatus($eventId, 'updated');
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

    public function handleDelete(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::DELETE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::DELETE_NONCE_FIELD], self::DELETE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $messageId = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;

        try {
            $this->messages->delete(
                $messageId,
                $eventId,
                isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin'
            );

            $this->redirectWithStatus($eventId, 'deleted');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage());
        }
    }

    public function renderPage(): void {
        $this->assertAdminPermissions();

        $events = $this->events->listEvents();
        $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
        $scope = isset($_GET['scope']) ? sanitize_key(wp_unslash((string) $_GET['scope'])) : 'all';
        $sortBy = $this->normalizeSortBy(isset($_GET['sort_by']) ? sanitize_key(wp_unslash((string) $_GET['sort_by'])) : 'id');
        $sortOrder = $this->normalizeSortOrder(isset($_GET['sort_order']) ? sanitize_key(wp_unslash((string) $_GET['sort_order'])) : 'desc');
        if (!in_array($scope, ['all', 'event', 'global'], true)) {
            $scope = 'all';
        }
        if ($eventId <= 0 && !empty($events)) {
            $eventId = (int) $events[0]->id;
        }

        $messages = $eventId > 0 ? $this->messages->listForEvent($eventId, 50, $scope) : [];
        $messages = $this->sortMessages($messages, $sortBy, $sortOrder);

        echo '<div class="wrap">';
        echo '<style>
            .bso-msg-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px;}
            .bso-msg-layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:20px;align-items:start;}
            .bso-msg-grid-card{background:#fff;border:1px solid #dcdcde;padding:0;}
            .bso-msg-grid-head{padding:14px 16px;border-bottom:1px solid #dcdcde;display:flex;justify-content:space-between;align-items:center;}
            .bso-msg-grid-title{margin:0;font-size:18px;}
            .bso-msg-toolbar .button{margin-left:4px;}
            .bso-msg-table-wrap{overflow:auto;}
            .bso-msg-table .column-action{white-space:nowrap;width:90px;}
            .bso-msg-row{cursor:pointer;}
            .bso-msg-row:hover td{background:#f6f7ff;}
            .bso-msg-row:focus td{outline:2px solid #93c5fd;outline-offset:-2px;}
            .bso-msg-row td:first-child{position:relative;padding-left:24px;}
            .bso-msg-row td:first-child::before{content:"↗";position:absolute;left:8px;top:50%;transform:translateY(-50%);opacity:0;color:#64748b;transition:opacity .15s ease,color .15s ease;}
            .bso-msg-row:hover td:first-child::before,
            .bso-msg-row:focus td:first-child::before,
            .bso-msg-row.is-selected td:first-child::before{opacity:1;color:#1d4ed8;}
            .bso-msg-row.is-selected td{background:#eef4ff;}
            .bso-msg-row.is-selected td:first-child{box-shadow:inset 4px 0 0 #1d4ed8;font-weight:600;}
            .bso-msg-sort-link{text-decoration:none;display:inline-flex;align-items:center;gap:4px;}
            .bso-msg-sort-arrow{font-size:12px;opacity:1;color:#9ca3af;line-height:1;min-width:10px;display:inline-block;}
            .bso-msg-sort-link.is-active .bso-msg-sort-arrow{color:#111827;}
            .bso-msg-action form{display:inline;}
            .bso-msg-action-link{color:#b32d2e;text-decoration:underline;background:none;border:none;padding:0;cursor:pointer;}
            .bso-msg-flipover{background:#fff;color:#1f2937;border:1px solid #dcdcde;padding:14px;position:sticky;top:40px;}
            .bso-msg-flipover.is-hidden{display:none;}
            .bso-msg-flipover h2{color:#111827;margin:0 0 12px;font-size:30px;line-height:1.1;}
            .bso-msg-flipover p{margin:0 0 10px;}
            .bso-msg-flipover label{display:block;margin-bottom:6px;font-weight:600;}
            .bso-msg-flipover input,
            .bso-msg-flipover select,
            .bso-msg-flipover textarea{width:100%;max-width:100%;}
            .bso-msg-flipover .bso-msg-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;}
            .bso-msg-flipover .bso-msg-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;}
            .bso-msg-flipover textarea{min-height:86px;}
            .bso-msg-flipover .bso-msg-buttons{display:flex;gap:10px;justify-content:flex-end;margin-top:12px;}
            .bso-msg-flipover .button{white-space:nowrap;}
            .bso-msg-mode-label{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;}
            .bso-msg-id-label{margin:0 0 10px;color:#374151;font-size:13px;}
            .bso-msg-id-label.is-hidden{display:none;}
            .bso-msg-empty{padding:12px 16px;}
            @media (max-width: 1200px){
                .bso-msg-layout{grid-template-columns:1fr;}
                .bso-msg-flipover{position:static;}
            }
        </style>';
        echo '<h1>' . esc_html__('Dashboard Meldingen', 'bso-survival') . '</h1>';

        if (isset($_GET['saved']) && $_GET['saved'] === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Melding opgeslagen.', 'bso-survival') . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Melding bijgewerkt.', 'bso-survival') . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Melding verwijderd.', 'bso-survival') . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'error') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : __('Onbekende fout.', 'bso-survival');
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="bso-msg-toolbar">';
        echo '<input type="hidden" name="page" value="bso-survival-dashboard-messages" />';
        echo '<input type="hidden" name="sort_by" value="' . esc_attr($sortBy) . '" />';
        echo '<input type="hidden" name="sort_order" value="' . esc_attr($sortOrder) . '" />';
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
        echo '<button type="button" class="button button-primary" id="bso-msg-open-create">' . esc_html__('Nieuw', 'bso-survival') . '</button>';
        echo '</form>';

        echo '<div class="bso-msg-layout">';
        echo '<section class="bso-msg-grid-card">';
        echo '<div class="bso-msg-grid-head">';
        echo '<h2 class="bso-msg-grid-title">' . esc_html__('Bestaande meldingen', 'bso-survival') . '</h2>';
        echo '<span>' . esc_html(sprintf(__('Totaal: %d', 'bso-survival'), count($messages))) . '</span>';
        echo '</div>';

        if ($messages === []) {
            echo '<p class="bso-msg-empty">' . esc_html__('Nog geen meldingen voor dit event.', 'bso-survival') . '</p>';
        } else {
            echo '<div class="bso-msg-table-wrap">';
            echo '<table class="widefat striped bso-msg-table">';
            echo '<thead><tr>';
            echo '<th>' . $this->renderSortableHeader(__('ID', 'bso-survival'), 'id', $sortBy, $sortOrder, $eventId, $scope) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Type', 'bso-survival'), 'type', $sortBy, $sortOrder, $eventId, $scope) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Prioriteit', 'bso-survival'), 'priority', $sortBy, $sortOrder, $eventId, $scope) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Scope', 'bso-survival'), 'scope', $sortBy, $sortOrder, $eventId, $scope) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Tekst', 'bso-survival'), 'text', $sortBy, $sortOrder, $eventId, $scope) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Status', 'bso-survival'), 'status', $sortBy, $sortOrder, $eventId, $scope) . '</th>';
            echo '<th class="column-action">' . esc_html__('Actie', 'bso-survival') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($messages as $row) {
                $currentStatus = (string) ($row->status ?? 'inactief');
                $type = (string) ($row->type ?? 'info');
                $priority = $this->priorityForType($type);
                $rowScope = $this->scopeForRow($row);
                $messageId = (int) ($row->id ?? 0);
                $editRowLabel = sprintf(__('Bewerk melding #%d', 'bso-survival'), $messageId);

                echo '<tr class="bso-msg-row" tabindex="0" role="button" aria-label="' . esc_attr($editRowLabel) . '" data-message-id="' . $messageId . '" data-type="' . esc_attr($type) . '" data-scope="' . esc_attr($rowScope) . '" data-status="' . esc_attr($currentStatus) . '" data-visibility="' . esc_attr((string) ($row->visibility ?? 'intern')) . '" data-visible-from="' . esc_attr($this->formatDateTimeLocal((string) ($row->visible_from ?? ''))) . '" data-visible-until="' . esc_attr($this->formatDateTimeLocal((string) ($row->visible_until ?? ''))) . '" data-text="' . esc_attr((string) ($row->text ?? '')) . '">';
                echo '<td>' . $messageId . '</td>';
                echo '<td>' . esc_html($type) . '</td>';
                echo '<td>' . (int) $priority . '</td>';
                echo '<td>' . esc_html($rowScope) . '</td>';
                echo '<td>' . esc_html((string) ($row->text ?? '')) . '</td>';
                echo '<td>' . esc_html($currentStatus) . '</td>';
                echo '<td class="bso-msg-action">';

                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Weet je zeker dat je deze melding wilt verwijderen?', 'bso-survival')) . '\');">';
                echo '<input type="hidden" name="action" value="bso_survival_dashboard_message_delete" />';
                echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
                echo '<input type="hidden" name="message_id" value="' . $messageId . '" />';
                echo '<input type="hidden" name="changed_by" value="admin" />';
                wp_nonce_field(self::DELETE_NONCE_ACTION, self::DELETE_NONCE_FIELD);
                echo '<button class="bso-msg-action-link" type="submit">' . esc_html__('Delete', 'bso-survival') . '</button>';
                echo '</form>';

                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
        }

        echo '</section>';

        echo '<aside class="bso-msg-flipover is-hidden" id="bso-msg-flipover">';
        echo '<h2 id="bso-msg-editor-title">' . esc_html__('Nieuwe melding', 'bso-survival') . '</h2>';
        echo '<p class="bso-msg-mode-label" id="bso-msg-editor-mode">' . esc_html__('Create mode', 'bso-survival') . '</p>';
        echo '<p class="bso-msg-id-label is-hidden" id="bso-msg-editor-id-label">' . esc_html__('Melding ID:', 'bso-survival') . ' <strong id="bso-msg-editor-id-value"></strong></p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="bso-msg-editor-form">';
        echo '<input type="hidden" id="bso-msg-editor-action" name="action" value="bso_survival_dashboard_message_create" />';
        echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
        echo '<input type="hidden" id="bso-msg-editor-id" name="message_id" value="" />';
        echo '<input type="hidden" name="changed_by" value="admin" />';
        wp_nonce_field(self::CREATE_NONCE_ACTION, self::CREATE_NONCE_FIELD);
        wp_nonce_field(self::UPDATE_NONCE_ACTION, self::UPDATE_NONCE_FIELD);

        echo '<p><label for="bso-msg-scope">' . esc_html__('Scope', 'bso-survival') . '</label>';
        echo '<select id="bso-msg-scope" name="scope">';
        echo '<option value="event">event-specifiek</option>';
        echo '<option value="global">global</option>';
        echo '</select></p>';

        echo '<div class="bso-msg-grid-3">';
        echo '<p><label for="bso-msg-status">' . esc_html__('Status', 'bso-survival') . '</label>';
        echo '<select id="bso-msg-status" name="status">';
        echo '<option value="actief">actief</option>';
        echo '<option value="inactief">inactief</option>';
        echo '</select></p>';

        echo '<p><label for="bso-msg-type">' . esc_html__('Type', 'bso-survival') . '</label>';
        echo '<select id="bso-msg-type" name="type">';
        echo '<option value="info">info</option>';
        echo '<option value="warning">warning</option>';
        echo '<option value="success">success</option>';
        echo '<option value="urgent">urgent</option>';
        echo '</select></p>';

        echo '<p><label for="bso-msg-visibility">' . esc_html__('Zichtbaarheid', 'bso-survival') . '</label>';
        echo '<select id="bso-msg-visibility" name="visibility">';
        echo '<option value="intern">intern</option>';
        echo '<option value="publiek">publiek</option>';
        echo '</select></p>';
        echo '</div>';

        echo '<div class="bso-msg-grid-2">';
        echo '<p><label for="bso-msg-visible-from">' . esc_html__('Zichtbaar vanaf', 'bso-survival') . '</label>';
        echo '<input id="bso-msg-visible-from" type="datetime-local" name="visible_from" value="" /></p>';

        echo '<p><label for="bso-msg-visible-until">' . esc_html__('Zichtbaar tot', 'bso-survival') . '</label>';
        echo '<input id="bso-msg-visible-until" type="datetime-local" name="visible_until" value="" /></p>';
        echo '</div>';

        echo '<p><label for="bso-msg-text">' . esc_html__('Meldingtekst', 'bso-survival') . '</label>';
        echo '<textarea id="bso-msg-text" name="text" rows="4" required="required"></textarea></p>';

        echo '<div class="bso-msg-buttons">';
        echo '<button class="button button-secondary" id="bso-msg-editor-close" type="button">' . esc_html__('Sluiten', 'bso-survival') . '</button>';
        echo '<button class="button button-primary" id="bso-msg-editor-submit" type="submit">' . esc_html__('Opslaan', 'bso-survival') . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</aside>';
        echo '</div>';

        echo '<script>
            (function(){
                var tableRows = document.querySelectorAll(".bso-msg-row");
                var flipover = document.getElementById("bso-msg-flipover");
                var editorTitle = document.getElementById("bso-msg-editor-title");
                var editorMode = document.getElementById("bso-msg-editor-mode");
                var editorIdLabel = document.getElementById("bso-msg-editor-id-label");
                var editorIdValue = document.getElementById("bso-msg-editor-id-value");
                var editorAction = document.getElementById("bso-msg-editor-action");
                var editorId = document.getElementById("bso-msg-editor-id");
                var scope = document.getElementById("bso-msg-scope");
                var status = document.getElementById("bso-msg-status");
                var type = document.getElementById("bso-msg-type");
                var visibility = document.getElementById("bso-msg-visibility");
                var visibleFrom = document.getElementById("bso-msg-visible-from");
                var visibleUntil = document.getElementById("bso-msg-visible-until");
                var text = document.getElementById("bso-msg-text");
                var submitButton = document.getElementById("bso-msg-editor-submit");
                var openCreateButton = document.getElementById("bso-msg-open-create");
                var closeButton = document.getElementById("bso-msg-editor-close");

                function setFlipVisible(isVisible){
                    if (!flipover) { return; }
                    flipover.classList.toggle("is-hidden", !isVisible);
                }

                function switchToCreate(){
                    if (!editorAction) { return; }
                    editorAction.value = "bso_survival_dashboard_message_create";
                    if (editorId) { editorId.value = ""; }
                    if (editorTitle) { editorTitle.textContent = "Nieuwe melding"; }
                    if (editorMode) { editorMode.textContent = "Create mode"; }
                    if (editorIdLabel) { editorIdLabel.classList.add("is-hidden"); }
                    if (editorIdValue) { editorIdValue.textContent = ""; }
                    if (scope) { scope.value = "event"; }
                    if (status) { status.value = "actief"; }
                    if (type) { type.value = "info"; }
                    if (visibility) { visibility.value = "intern"; }
                    if (visibleFrom) { visibleFrom.value = ""; }
                    if (visibleUntil) { visibleUntil.value = ""; }
                    if (text) { text.value = ""; }
                    if (submitButton) { submitButton.textContent = "Opslaan"; }
                    tableRows.forEach(function(row){ row.classList.remove("is-selected"); });
                }

                function switchToEdit(row){
                    if (!row || !editorAction) { return; }
                    editorAction.value = "bso_survival_dashboard_message_update";
                    if (editorId) { editorId.value = row.getAttribute("data-message-id") || ""; }
                    if (editorTitle) { editorTitle.textContent = "Melding bewerken"; }
                    if (editorMode) { editorMode.textContent = "Edit mode"; }
                    if (editorIdLabel) { editorIdLabel.classList.remove("is-hidden"); }
                    if (editorIdValue) { editorIdValue.textContent = row.getAttribute("data-message-id") || ""; }
                    if (scope) { scope.value = row.getAttribute("data-scope") || "event"; }
                    if (status) { status.value = row.getAttribute("data-status") || "actief"; }
                    if (type) { type.value = row.getAttribute("data-type") || "info"; }
                    if (visibility) { visibility.value = row.getAttribute("data-visibility") || "intern"; }
                    if (visibleFrom) { visibleFrom.value = row.getAttribute("data-visible-from") || ""; }
                    if (visibleUntil) { visibleUntil.value = row.getAttribute("data-visible-until") || ""; }
                    if (text) { text.value = row.getAttribute("data-text") || ""; }
                    if (submitButton) { submitButton.textContent = "Bijwerken"; }
                    tableRows.forEach(function(item){ item.classList.remove("is-selected"); });
                    row.classList.add("is-selected");
                    setFlipVisible(true);
                }

                tableRows.forEach(function(row){
                    row.addEventListener("click", function(event){
                        var target = event.target;
                        if (target && target.closest(".bso-msg-action")) {
                            return;
                        }

                        switchToEdit(row);
                    });

                    row.addEventListener("keydown", function(event){
                        if (event.key !== "Enter" && event.key !== " ") {
                            return;
                        }

                        event.preventDefault();
                        switchToEdit(row);
                    });
                });

                if (openCreateButton) {
                    openCreateButton.addEventListener("click", function(){
                        switchToCreate();
                        setFlipVisible(true);
                        if (text) {
                            text.focus();
                        }
                    });
                }

                if (closeButton) {
                    closeButton.addEventListener("click", function(){
                        setFlipVisible(false);
                    });
                }

                switchToCreate();
                setFlipVisible(false);
            })();
        </script>';
        echo '</div>';
    }

    /**
     * @param array<int, object> $messages
     * @return array<int, object>
     */
    private function sortMessages(array $messages, string $sortBy, string $sortOrder): array {
        usort($messages, function ($left, $right) use ($sortBy, $sortOrder): int {
            $a = $this->messageSortValue($left, $sortBy);
            $b = $this->messageSortValue($right, $sortBy);

            if (is_numeric($a) && is_numeric($b)) {
                $cmp = ((float) $a <=> (float) $b);
            } else {
                $cmp = strcmp((string) $a, (string) $b);
            }

            if ($cmp === 0) {
                $cmp = ((int) ($left->id ?? 0) <=> (int) ($right->id ?? 0));
            }

            return $sortOrder === 'asc' ? $cmp : -$cmp;
        });

        return $messages;
    }

    /** @param object $row */
    private function messageSortValue($row, string $sortBy) {
        switch ($sortBy) {
            case 'id':
                return (int) ($row->id ?? 0);
            case 'type':
                return (string) ($row->type ?? '');
            case 'priority':
                return $this->priorityForType((string) ($row->type ?? ''));
            case 'scope':
                return $this->scopeForRow($row);
            case 'text':
                return (string) ($row->text ?? '');
            case 'status':
                return (string) ($row->status ?? '');
            default:
                return (int) ($row->id ?? 0);
        }
    }

    /** @param object $row */
    private function scopeForRow($row): string {
        $scope = (string) ($row->scope ?? '');
        if ($scope === 'event' || $scope === 'global') {
            return $scope;
        }

        return (string) ($row->visibility ?? '') === 'global' ? 'global' : 'event';
    }

    private function renderSortableHeader(string $label, string $column, string $sortBy, string $sortOrder, int $eventId, string $scope): string {
        $nextOrder = ($sortBy === $column && $sortOrder === 'asc') ? 'desc' : 'asc';
        $isActive = $sortBy === $column;
        $arrow = '↕';

        if ($isActive) {
            $arrow = $sortOrder === 'asc' ? '▲' : '▼';
        }

        $url = add_query_arg([
            'page' => 'bso-survival-dashboard-messages',
            'event_id' => $eventId,
            'scope' => $scope,
            'sort_by' => $column,
            'sort_order' => $nextOrder,
        ], admin_url('admin.php'));

        return '<a class="bso-msg-sort-link' . ($isActive ? ' is-active' : '') . '" href="' . esc_url($url) . '">' .
            esc_html($label) .
            '<span class="bso-msg-sort-arrow">' . esc_html($arrow) . '</span>' .
            '</a>';
    }

    private function normalizeSortBy(string $value): string {
        if (!in_array($value, ['id', 'type', 'priority', 'scope', 'text', 'status'], true)) {
            return 'id';
        }

        return $value;
    }

    private function normalizeSortOrder(string $value): string {
        return $value === 'asc' ? 'asc' : 'desc';
    }

    private function assertAdminPermissions(): void {
        if (!Capabilities::canManageMessages()) {
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

    private function formatDateTimeLocal(string $value): string {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return gmdate('Y-m-d\TH:i', $timestamp);
    }
}
