<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Database\Repository\EventPublicationRepository;
use BSO\Survival\Database\Repository\RegistrationWindowRepository;
use BSO\Survival\Service\EventAdminService;
use BSO\Survival\Service\EventPublicationService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartRuleConfiguratorService;
use BSO\Survival\Service\ScoringMethodRegistry;
use BSO\Survival\Support\Capabilities;
use RuntimeException;

class EventAdminPage {
    private const CREATE_NONCE_ACTION = 'bso_survival_event_create';
    private const CREATE_NONCE_FIELD = 'bso_survival_event_create_nonce';
    private const UPDATE_NONCE_ACTION = 'bso_survival_event_update';
    private const UPDATE_NONCE_FIELD = 'bso_survival_event_update_nonce';
    private const LINK_PARTS_NONCE_ACTION = 'bso_survival_event_link_parts';
    private const LINK_PARTS_NONCE_FIELD = 'bso_survival_event_link_parts_nonce';
    private const DELETE_NONCE_ACTION = 'bso_survival_event_delete';
    private const DELETE_NONCE_FIELD = 'bso_survival_event_delete_nonce';
    private const SAVE_PART_RULE_NONCE_ACTION = 'bso_survival_event_part_rule_save';
    private const SAVE_PART_RULE_NONCE_FIELD = 'bso_survival_event_part_rule_save_nonce';

    /** @var EventService */
    private $events;

    /** @var EventAdminService */
    private $admin;

    /** @var EventPublicationService */
    private $publications;

    /** @var PartRuleConfiguratorService */
    private $partRuleConfigurator;

    /** @var object */
    private $partRules;

    /** @var RegistrationWindowRepository */
    private $registrationWindows;

    public function __construct(EventService $events, EventAdminService $admin, PartRuleConfiguratorService $partRuleConfigurator, $partRulesRepository, ?EventPublicationService $publications = null, ?RegistrationWindowRepository $registrationWindows = null) {
        $this->events = $events;
        $this->admin = $admin;
        $this->partRuleConfigurator = $partRuleConfigurator;
        $this->partRules = $partRulesRepository;
        $this->publications = $publications ?? new EventPublicationService(new EventPublicationRepository());
        $this->registrationWindows = $registrationWindows ?? new RegistrationWindowRepository();
    }

    public function registerMenu(): void {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'bso-survival-rules',
            __('Events', 'bso-survival'),
            __('Events', 'bso-survival'),
            Capabilities::MANAGE_SETTINGS,
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
            $result = $this->admin->createEventWithSetup(
                isset($_POST['event_name']) ? sanitize_text_field(wp_unslash((string) $_POST['event_name'])) : '',
                isset($_POST['event_date']) ? sanitize_text_field(wp_unslash((string) $_POST['event_date'])) : '',
                isset($_POST['max_teams']) ? (int) $_POST['max_teams'] : 22,
                [
                    'create_demo_teams' => isset($_POST['create_demo_teams']) && (string) $_POST['create_demo_teams'] === '1',
                    'demo_teams_count' => isset($_POST['demo_teams_count']) ? (int) $_POST['demo_teams_count'] : 22,
                    'link_all_parts' => isset($_POST['link_all_parts']) && (string) $_POST['link_all_parts'] === '1',
                    'generate_scores' => isset($_POST['generate_scores']) && (string) $_POST['generate_scores'] === '1',
                ]
            );

            $created = $result['event'];
            $summary = $result['summary'];
            $message = $this->buildCreateSummaryMessage($summary);

            $this->redirectWithStatus((int) ($created->id ?? 0), 'created', $message);
        } catch (\Throwable $exception) {
            $this->redirectWithStatus(0, 'error', $exception->getMessage(), 'create');
        }
    }

    public function handleUpdate(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::UPDATE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::UPDATE_NONCE_FIELD], self::UPDATE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $registrationWindowEnabled = isset($_POST['registration_window_enabled']) && (string) $_POST['registration_window_enabled'] === '1';
        $registrationWindowOpensOn = isset($_POST['registration_window_opens_on']) ? sanitize_text_field(wp_unslash((string) $_POST['registration_window_opens_on'])) : '';
        $registrationWindowClosesOn = isset($_POST['registration_window_closes_on']) ? sanitize_text_field(wp_unslash((string) $_POST['registration_window_closes_on'])) : '';

        try {
            $updated = $this->admin->updateEvent(
                $eventId,
                isset($_POST['event_name']) ? sanitize_text_field(wp_unslash((string) $_POST['event_name'])) : '',
                isset($_POST['event_date']) ? sanitize_text_field(wp_unslash((string) $_POST['event_date'])) : '',
                isset($_POST['max_teams']) ? (int) $_POST['max_teams'] : 22
            );

            $eventDate = trim((string) ($updated->event_date ?? ''));
            $windowSaved = $this->saveRegistrationWindow($eventId, $eventDate, $registrationWindowEnabled, $registrationWindowOpensOn, $registrationWindowClosesOn);
            if ($windowSaved === false) {
                throw new RuntimeException('Inschrijvingsperiode kon niet worden opgeslagen.');
            }

            $this->redirectWithStatus((int) ($updated->id ?? $eventId), 'updated');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage(), 'edit');
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

    public function handlePartRuleSave(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::SAVE_PART_RULE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::SAVE_PART_RULE_NONCE_FIELD], self::SAVE_PART_RULE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $partId = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $partFilter = isset($_POST['part_filter']) ? sanitize_text_field(wp_unslash((string) $_POST['part_filter'])) : '';
        $partSortBy = isset($_POST['part_sort_by']) ? sanitize_key(wp_unslash((string) $_POST['part_sort_by'])) : 'part_name';
        $partSortDirection = isset($_POST['part_sort_direction']) ? sanitize_key(wp_unslash((string) $_POST['part_sort_direction'])) : 'asc';

        if ($eventId <= 0 || $partId <= 0) {
            $this->redirectWithPartPanelState($eventId, $partId, $partFilter, $partSortBy, $partSortDirection, 'error', __('Ongeldige event/part selectie.', 'bso-survival'));
        }

        $event = $this->events->getEvent($eventId);
        $status = is_object($event) ? (string) ($event->status ?? '') : '';
        $normalizedStatus = function_exists('mb_strtolower') ? mb_strtolower(trim($status)) : strtolower(trim($status));
        if (in_array($normalizedStatus, ['afgesloten', 'gesloten', 'closed', 'gepubliceerd', 'verwijderd'], true)) {
            $this->redirectWithPartPanelState($eventId, $partId, $partFilter, $partSortBy, $partSortDirection, 'error', __('Dit event is read-only. Instellingen kunnen niet worden aangepast.', 'bso-survival'));
        }

        $assignedParts = $this->admin->listAssignedPartsForEvent($eventId);
        $isPersistedLink = false;
        foreach ($assignedParts as $assignedPart) {
            if ((int) ($assignedPart->id ?? 0) === $partId) {
                $isPersistedLink = true;
                break;
            }
        }

        if (!$isPersistedLink) {
            $this->redirectWithPartPanelState(
                $eventId,
                $partId,
                $partFilter,
                $partSortBy,
                $partSortDirection,
                'error',
                __('Dit onderdeel is nog niet opgeslagen als koppeling bij dit event. Sla eerst de part-koppelingen op en open daarna opnieuw de onderdeelinstellingen.', 'bso-survival')
            );
        }

        $mode = isset($_POST['scoring_mode']) ? sanitize_key(wp_unslash((string) $_POST['scoring_mode'])) : '';
        $tiebreakerMode = isset($_POST['tiebreaker_mode']) ? sanitize_key(wp_unslash((string) $_POST['tiebreaker_mode'])) : 'manual_referee';
        $config = [
            'max_time' => isset($_POST['max_time']) ? (int) $_POST['max_time'] : null,
            'max_points' => isset($_POST['max_points']) ? (int) $_POST['max_points'] : null,
            'max_distance' => isset($_POST['max_distance']) ? (int) $_POST['max_distance'] : null,
            'normalization_curve' => isset($_POST['normalization_curve'])
                ? sanitize_key(wp_unslash((string) $_POST['normalization_curve']))
                : 'linear',
        ];

        try {
            $this->partRuleConfigurator->configure($partId, $mode, $config, $tiebreakerMode);
            $this->redirectWithPartPanelState($eventId, $partId, $partFilter, $partSortBy, $partSortDirection, 'part_rule_saved');
        } catch (\Throwable $exception) {
            $this->redirectWithPartPanelState($eventId, $partId, $partFilter, $partSortBy, $partSortDirection, 'error', $exception->getMessage());
        }
    }

    public function renderPage(): void {
        $this->assertAdminPermissions();

        $panelMode = isset($_GET['event_panel']) ? sanitize_key((string) wp_unslash($_GET['event_panel'])) : '';
        if (!in_array($panelMode, ['create', 'edit', 'part'], true)) {
            $panelMode = '';
        }
        $selectedPartId = isset($_GET['part_id']) ? (int) $_GET['part_id'] : 0;

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
        $normalizedStatus = function_exists('mb_strtolower') ? mb_strtolower(trim($status)) : strtolower(trim($status));
        $isImmutable = in_array($normalizedStatus, ['afgesloten', 'gesloten', 'closed', 'gepubliceerd', 'verwijderd'], true);
        $partFilter = isset($_GET['part_filter']) ? sanitize_text_field(wp_unslash((string) $_GET['part_filter'])) : '';
        $partSortBy = isset($_GET['part_sort_by']) ? sanitize_key((string) wp_unslash($_GET['part_sort_by'])) : 'part_name';
        $partSortDirection = isset($_GET['part_sort_direction']) ? sanitize_key((string) wp_unslash($_GET['part_sort_direction'])) : 'asc';
        if (!in_array($partSortBy, ['linked', 'part_name', 'owner_event'], true)) {
            $partSortBy = 'part_name';
        }
        if (!in_array($partSortDirection, ['asc', 'desc'], true)) {
            $partSortDirection = 'asc';
        }
        if ($selectedEvent !== null && $isImmutable) {
            $allEligibleParts = $this->admin->listAssignedPartsForEvent($selectedEventId);
            $parts = $this->admin->listAssignedPartsForEvent($selectedEventId, $partFilter);
        } else {
            $allEligibleParts = $selectedEvent !== null ? $this->admin->listEligiblePartsForEvent($selectedEventId) : [];
            $parts = $selectedEvent !== null ? $this->admin->listEligiblePartsForEvent($selectedEventId, $partFilter) : [];
        }

        $createPanelUrl = $this->buildAdminUrl([
            'event_panel' => 'create',
            'event_id' => $selectedEventId,
            'part_filter' => $partFilter,
            'part_sort_by' => $partSortBy,
            'part_sort_direction' => $partSortDirection,
        ]);
        $editPanelUrl = $this->buildAdminUrl([
            'event_panel' => 'edit',
            'event_id' => $selectedEventId,
            'part_filter' => $partFilter,
            'part_sort_by' => $partSortBy,
            'part_sort_direction' => $partSortDirection,
        ]);
        $closePanelUrl = $this->buildAdminUrl([
            'event_id' => $selectedEventId,
            'part_filter' => $partFilter,
            'part_sort_by' => $partSortBy,
            'part_sort_direction' => $partSortDirection,
        ]);

        echo '<div class="wrap">';
        echo '<style>
            .bso-events-layout{position:relative;}
            .bso-events-main{max-width:100%;transition:margin-right .2s ease;}
            .bso-events-main.with-panel{margin-right:380px;}
            .bso-events-toolbar{display:flex;align-items:center;gap:10px;margin:10px 0 14px 0;}
            .bso-events-toolbar-actions{display:inline-flex;gap:8px;flex-wrap:wrap;align-items:center;margin-left:8px;vertical-align:middle;}
            .bso-events-sort-link{text-decoration:none;display:inline-flex;align-items:center;gap:4px;}
            .bso-events-sort-arrow{font-size:12px;opacity:1;color:#9ca3af;line-height:1;min-width:10px;display:inline-block;}
            .bso-events-sort-link.is-active .bso-events-sort-arrow{color:#111827;}
            .bso-events-panel{position:fixed;top:32px;right:0;width:360px;height:calc(100vh - 32px);background:#fff;border-left:1px solid #dcdcde;z-index:999;padding:14px 16px 16px 16px;overflow:auto;box-shadow:-6px 0 20px rgba(0,0,0,.08);}
            .bso-events-panel-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
            .bso-events-panel-title{font-size:20px;font-weight:600;margin:0;}
            .bso-switch{display:inline-flex;align-items:center;gap:10px;font-weight:600;}
            .bso-switch input{position:absolute;opacity:0;pointer-events:none;}
            .bso-switch-track{position:relative;display:inline-block;width:44px;height:24px;background:#b4b9be;border-radius:999px;transition:background .18s ease;flex:0 0 auto;}
            .bso-switch-thumb{position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;border-radius:50%;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:transform .18s ease;}
            .bso-switch input:checked + .bso-switch-track{background:#2271b1;}
            .bso-switch input:checked + .bso-switch-track .bso-switch-thumb{transform:translateX(20px);}
            .bso-window-hint{margin-top:8px;color:#646970;font-size:12px;line-height:1.4;}
            @media (max-width: 1200px){
                .bso-events-main.with-panel{margin-right:0;}
                .bso-events-panel{position:static;width:auto;height:auto;box-shadow:none;border:1px solid #dcdcde;margin-top:14px;}
            }
        </style>';
        echo '<h1>' . esc_html__('Survival Events', 'bso-survival') . '</h1>';

        if (isset($_GET['saved']) && $_GET['saved'] === 'created') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : __('Event aangemaakt.', 'bso-survival');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
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
        if (isset($_GET['saved']) && $_GET['saved'] === 'part_rule_saved') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Onderdeelinstellingen opgeslagen.', 'bso-survival') . '</p></div>';
        }
        if (isset($_GET['saved']) && $_GET['saved'] === 'error') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : __('Onbekende fout.', 'bso-survival');
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<div class="bso-events-layout">';
        echo '<div class="bso-events-main' . ($panelMode !== '' ? ' with-panel' : '') . '">';

        echo '<div class="bso-events-toolbar">';
        echo '<h2 style="margin:0;">' . esc_html__('Bestaand event beheren', 'bso-survival') . '</h2>';
        echo '</div>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-survival-events" />';
        echo '<input type="hidden" name="part_sort_by" value="' . esc_attr($partSortBy) . '" />';
        echo '<input type="hidden" name="part_sort_direction" value="' . esc_attr($partSortDirection) . '" />';
        if ($panelMode !== '') {
            echo '<input type="hidden" name="event_panel" value="' . esc_attr($panelMode) . '" />';
        }
        if ($partFilter !== '') {
            echo '<input type="hidden" name="part_filter" value="' . esc_attr($partFilter) . '" />';
        }
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
        echo '<span class="bso-events-toolbar-actions">';
        echo '<a class="button" href="' . esc_url($createPanelUrl) . '">' . esc_html__('Nieuw event aanmaken', 'bso-survival') . '</a>';
        if ($selectedEvent !== null) {
            echo '<a class="button button-secondary" href="' . esc_url($editPanelUrl) . '">' . esc_html__('Event bewerken', 'bso-survival') . '</a>';
        }
        echo '</span>';
        echo '</form>';

        if ($selectedEvent !== null && $isImmutable) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Dit event is afgesloten/gepubliceerd/verwijderd en mag niet meer inhoudelijk aangepast worden.', 'bso-survival') . '</p></div>';
            $this->renderPublicationSummary($selectedEventId);
        }

        if ($selectedEvent !== null) {
            $selectedEventId = (int) ($selectedEvent->id ?? 0);
            $attachedLookup = [];
            foreach ($allEligibleParts as $part) {
                $partEventId = isset($part->event_id) ? (int) $part->event_id : 0;
                if ($partEventId === $selectedEventId) {
                    $attachedLookup[(int) ($part->id ?? 0)] = true;
                }
            }

            $parts = $this->sortPartsForGrid($parts, $selectedEventId, $partSortBy, $partSortDirection);

            $visibleIds = [];
            foreach ($parts as $part) {
                $visibleIds[(int) ($part->id ?? 0)] = true;
            }

            echo '<h3>' . esc_html__('Parts koppelen aan dit event', 'bso-survival') . '</h3>';
            echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="margin:12px 0;max-width:980px;">';
            echo '<input type="hidden" name="page" value="bso-survival-events" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $selectedEventId . '" />';
            echo '<input type="hidden" name="part_sort_by" value="' . esc_attr($partSortBy) . '" />';
            echo '<input type="hidden" name="part_sort_direction" value="' . esc_attr($partSortDirection) . '" />';
            if ($panelMode !== '') {
                echo '<input type="hidden" name="event_panel" value="' . esc_attr($panelMode) . '" />';
            }
            echo '<label for="bso-survival-part-filter"><strong>' . esc_html__('Filter onderdelen', 'bso-survival') . ':</strong></label> ';
            echo '<input id="bso-survival-part-filter" type="search" name="part_filter" value="' . esc_attr($partFilter) . '" class="regular-text" placeholder="' . esc_attr__('Zoek op naam', 'bso-survival') . '" /> ';
            echo '<button class="button">' . esc_html__('Filter', 'bso-survival') . '</button> ';
            $resetArgs = ['page' => 'bso-survival-events', 'event_id' => $selectedEventId];
            $resetArgs['part_sort_by'] = $partSortBy;
            $resetArgs['part_sort_direction'] = $partSortDirection;
            if ($panelMode !== '') {
                $resetArgs['event_panel'] = $panelMode;
            }
            echo '<a class="button button-link" href="' . esc_url(add_query_arg($resetArgs, admin_url('admin.php'))) . '">' . esc_html__('Reset', 'bso-survival') . '</a>';
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
            echo '<th>' . $this->renderPartsSortLink('linked', __('Koppelen', 'bso-survival'), $partSortBy, $partSortDirection, $selectedEventId, $partFilter, $panelMode) . '</th>';
            echo '<th>' . $this->renderPartsSortLink('part_name', __('Part', 'bso-survival'), $partSortBy, $partSortDirection, $selectedEventId, $partFilter, $panelMode) . '</th>';
            echo '<th>' . $this->renderPartsSortLink('owner_event', __('Huidig event', 'bso-survival'), $partSortBy, $partSortDirection, $selectedEventId, $partFilter, $panelMode) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($parts as $part) {
                $partId = (int) ($part->id ?? 0);
                $ownerEventId = isset($part->event_id) ? (int) $part->event_id : 0;
                $ownerLabel = $ownerEventId > 0 ? ('#' . $ownerEventId) : __('niet gekoppeld', 'bso-survival');

                $checked = checked(isset($attachedLookup[$partId]), true, false);
                $disabled = $isImmutable ? ' disabled="disabled"' : '';

                echo '<tr>';
                echo '<td><input type="checkbox" name="part_ids[]" value="' . $partId . '" ' . $checked . $disabled . ' /></td>';
                    $partName = (string) ($part->name ?? ('Part #' . $partId));
                    if (isset($attachedLookup[$partId])) {
                        $partPanelArgs = [
                            'event_id' => $selectedEventId,
                            'event_panel' => 'part',
                            'part_id' => $partId,
                            'part_sort_by' => $partSortBy,
                            'part_sort_direction' => $partSortDirection,
                        ];
                        if ($partFilter !== '') {
                            $partPanelArgs['part_filter'] = $partFilter;
                        }
                        $partPanelUrl = $this->buildAdminUrl($partPanelArgs);
                        echo '<td><a href="' . esc_url($partPanelUrl) . '">' . esc_html($partName) . '</a></td>';
                    } else {
                        echo '<td>' . esc_html($partName) . '</td>';
                    }
                echo '<td>' . esc_html((string) $ownerLabel) . '</td>';
                echo '</tr>';
            }

            if ($parts === []) {
                $emptyMessage = $isImmutable
                    ? __('Geen gekoppelde onderdelen gevonden voor dit read-only event en huidige filter.', 'bso-survival')
                    : __('Geen geldige onderdelen gevonden voor dit event en huidige filter.', 'bso-survival');
                echo '<tr><td colspan="3">' . esc_html($emptyMessage) . '</td></tr>';
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

        if ($panelMode !== '') {
            $this->renderEventSidePanel($panelMode, $selectedEvent, $selectedEventId, $isImmutable, $closePanelUrl, $selectedPartId, $partFilter, $partSortBy, $partSortDirection, $attachedLookup ?? []);
        }

        echo '</div>';
        echo '</div>';
    }

    private function assertAdminPermissions(): void {
        if (!Capabilities::canManageSettings()) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }
    }

    private function redirectWithStatus(int $eventId, string $saved, string $message = '', string $panelMode = ''): void {
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

        if (in_array($panelMode, ['create', 'edit'], true)) {
            $args['event_panel'] = $panelMode;
        }

        $redirect = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    private function redirectWithPartPanelState(int $eventId, int $partId, string $partFilter, string $partSortBy, string $partSortDirection, string $saved, string $message = ''): void {
        $args = [
            'page' => 'bso-survival-events',
            'event_id' => $eventId,
            'event_panel' => 'part',
            'part_id' => $partId,
            'part_sort_by' => $partSortBy,
            'part_sort_direction' => $partSortDirection,
            'saved' => $saved,
        ];

        if ($partFilter !== '') {
            $args['part_filter'] = $partFilter;
        }

        if ($message !== '') {
            $args['message'] = $message;
        }

        $redirect = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    private function renderPublicationSummary(int $eventId): void {
        if ($eventId <= 0) {
            return;
        }

        $publication = $this->publications->getForEvent($eventId);

        echo '<div class="postbox" style="max-width:980px;margin:8px 0 18px 0;">';
        echo '<div class="postbox-header"><h2 class="hndle" style="margin:0;padding:10px 12px;">' . esc_html__('Samenvatting van dit event', 'bso-survival') . '</h2></div>';
        echo '<div class="inside">';

        if ($publication === null || $publication === []) {
            echo '<p>' . esc_html__('Nog geen samenvatting/publicatie beschikbaar voor dit event.', 'bso-survival') . '</p>';
            echo '</div></div>';
            return;
        }

        $headline = (string) ($publication['headline'] ?? '');
        $publishedAt = (string) ($publication['published_at'] ?? '');
        $topThree = isset($publication['top_3']) && is_array($publication['top_3']) ? $publication['top_3'] : [];
        $standings = isset($publication['final_standings']) && is_array($publication['final_standings']) ? $publication['final_standings'] : [];

        if ($headline !== '') {
            echo '<p><strong>' . esc_html__('Kopregel', 'bso-survival') . ':</strong> ' . esc_html($headline) . '</p>';
        }
        if ($publishedAt !== '') {
            echo '<p><strong>' . esc_html__('Gepubliceerd op', 'bso-survival') . ':</strong> ' . esc_html($publishedAt) . '</p>';
        }

        if ($topThree !== []) {
            echo '<h4>' . esc_html__('Top 3', 'bso-survival') . '</h4>';
            echo '<ol>';
            foreach ($topThree as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $rank = isset($row['rank']) ? (int) $row['rank'] : 0;
                $team = (string) ($row['team_name'] ?? 'Onbekend team');
                $points = isset($row['points']) ? (string) $row['points'] : '';
                $label = ($rank > 0 ? ('#' . $rank . ' ') : '') . $team;
                if ($points !== '') {
                    $label .= ' (' . $points . ')';
                }

                echo '<li>' . esc_html($label) . '</li>';
            }
            echo '</ol>';
        }

        if ($standings !== []) {
            echo '<h4>' . esc_html__('Eindstand (top 10)', 'bso-survival') . '</h4>';
            echo '<table class="widefat striped" style="max-width:560px;"><thead><tr>';
            echo '<th>' . esc_html__('Positie', 'bso-survival') . '</th>';
            echo '<th>' . esc_html__('Team', 'bso-survival') . '</th>';
            echo '<th>' . esc_html__('Punten', 'bso-survival') . '</th>';
            echo '</tr></thead><tbody>';

            $index = 0;
            foreach ($standings as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $index++;
                if ($index > 10) {
                    break;
                }

                $rank = isset($row['rank']) ? (int) $row['rank'] : $index;
                $team = (string) ($row['team_name'] ?? 'Onbekend team');
                $points = isset($row['points']) ? (string) $row['points'] : '-';

                echo '<tr>';
                echo '<td>' . esc_html((string) $rank) . '</td>';
                echo '<td>' . esc_html($team) . '</td>';
                echo '<td>' . esc_html($points) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div></div>';
    }

    /**
     * @param array<int, object> $parts
     * @return array<int, object>
     */
    private function sortPartsForGrid(array $parts, int $selectedEventId, string $sortBy, string $sortDirection): array {
        usort($parts, static function ($left, $right) use ($selectedEventId, $sortBy, $sortDirection): int {
            $leftId = (int) ($left->id ?? 0);
            $rightId = (int) ($right->id ?? 0);

            $leftLinked = ((int) ($left->event_id ?? 0) === $selectedEventId) ? 1 : 0;
            $rightLinked = ((int) ($right->event_id ?? 0) === $selectedEventId) ? 1 : 0;

            if ($sortBy === 'linked') {
                $cmp = $leftLinked <=> $rightLinked;
            } elseif ($sortBy === 'owner_event') {
                $cmp = ((int) ($left->event_id ?? 0)) <=> ((int) ($right->event_id ?? 0));
            } else {
                $cmp = strcmp((string) ($left->name ?? ''), (string) ($right->name ?? ''));
            }

            if ($cmp === 0) {
                $cmp = $leftId <=> $rightId;
            }

            return $sortDirection === 'desc' ? ($cmp * -1) : $cmp;
        });

        return $parts;
    }

    private function renderPartsSortLink(string $column, string $label, string $currentSortBy, string $currentDirection, int $eventId, string $partFilter, string $panelMode): string {
        $isActive = $column === $currentSortBy;
        $nextDirection = $isActive && $currentDirection === 'asc' ? 'desc' : 'asc';
        $indicator = '↕';
        if ($isActive) {
            $indicator = $currentDirection === 'asc' ? '▲' : '▼';
        }

        $args = [
            'event_id' => $eventId,
            'part_sort_by' => $column,
            'part_sort_direction' => $nextDirection,
        ];

        if ($partFilter !== '') {
            $args['part_filter'] = $partFilter;
        }

        if ($panelMode !== '') {
            $args['event_panel'] = $panelMode;
        }

        $url = $this->buildAdminUrl($args);
        return '<a class="bso-events-sort-link' . ($isActive ? ' is-active' : '') . '" href="' . esc_url($url) . '">' .
            esc_html($label) .
            '<span class="bso-events-sort-arrow">' . esc_html($indicator) . '</span>' .
            '</a>';
    }

    /**
     * @param array<string, mixed> $args
     */
    private function buildAdminUrl(array $args): string {
        $baseArgs = ['page' => 'bso-survival-events'];
        return add_query_arg(array_merge($baseArgs, $args), admin_url('admin.php'));
    }

    /** @param object|null $selectedEvent */
    private function renderEventSidePanel(string $panelMode, $selectedEvent, int $selectedEventId, bool $isImmutable, string $closePanelUrl, int $selectedPartId, string $partFilter, string $partSortBy, string $partSortDirection, array $attachedLookup): void {
        echo '<aside class="bso-events-panel">';
        echo '<div class="bso-events-panel-top">';
        $title = __('Eventgegevens bewerken', 'bso-survival');
        if ($panelMode === 'create') {
            $title = __('Nieuw event aanmaken', 'bso-survival');
        }
        if ($panelMode === 'part') {
            $eventName = is_object($selectedEvent) ? trim((string) ($selectedEvent->name ?? '')) : '';
            if ($eventName !== '') {
                $title = sprintf(__('Onderdeelinstellingen voor %s', 'bso-survival'), $eventName);
            } else {
                $title = __('Onderdeelinstellingen voor dit event', 'bso-survival');
            }
        }
        echo '<p class="bso-events-panel-title">' . esc_html($title) . '</p>';
        echo '<a class="button button-link" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a>';
        echo '</div>';

        if ($panelMode !== 'create' && $panelMode !== 'part' && $selectedEventId > 0) {
            echo '<p><strong>' . esc_html__('Event ID', 'bso-survival') . ':</strong> #' . (int) $selectedEventId . '</p>';
        }

        if ($panelMode === 'create') {
            $this->renderCreatePanelForm($closePanelUrl);
        } elseif ($panelMode === 'part') {
            $this->renderPartSettingsPanelForm($selectedEventId, $selectedPartId, $isImmutable, $closePanelUrl, $partFilter, $partSortBy, $partSortDirection, $attachedLookup);
        } else {
            $this->renderEditPanelForm($selectedEvent, $selectedEventId, $isImmutable, $closePanelUrl);
        }

        echo '</aside>';
    }

    private function renderCreatePanelForm(string $closePanelUrl): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_event_create" />';
        wp_nonce_field(self::CREATE_NONCE_ACTION, self::CREATE_NONCE_FIELD);

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="bso-event-name">' . esc_html__('Naam', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-event-name" name="event_name" type="text" class="regular-text" required="required" /></td></tr>';

        echo '<tr><th scope="row"><label for="bso-event-date">' . esc_html__('Datum', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-event-date" name="event_date" type="date" required="required" /></td></tr>';

        echo '<tr><th scope="row"><label for="bso-max-teams">' . esc_html__('Max teams', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-max-teams" name="max_teams" type="number" min="1" value="22" /></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Demo-opties', 'bso-survival') . '</th><td>';
        echo '<p><label><input id="bso-create-demo-teams" type="checkbox" name="create_demo_teams" value="1" /> ' . esc_html__('Demo teams aanmaken', 'bso-survival') . '</label><br />';
        echo '<input id="bso-demo-teams-count" name="demo_teams_count" type="number" min="1" value="22" style="width:110px;" />';
        echo ' <span class="description">' . esc_html__('Aantal demo teams voor dit event.', 'bso-survival') . '</span></p>';
        echo '<p><label><input id="bso-link-all-parts" type="checkbox" name="link_all_parts" value="1" /> ' . esc_html__('Alle beschikbare onderdelen koppelen', 'bso-survival') . '</label></p>';
        echo '<p><label><input id="bso-generate-scores" type="checkbox" name="generate_scores" value="1" /> ' . esc_html__('Planning + score-records genereren', 'bso-survival') . '</label><br />';
        echo '<span id="bso-generate-scores-hint" class="description">' . esc_html__('Vereist teams en gekoppelde onderdelen; gebruikt de bestaande planningsregels voor spreiding van tegenstanders.', 'bso-survival') . '</span></p>';
        echo '</td></tr>';
        echo '</tbody></table>';

        echo '<p><button class="button button-primary">' . esc_html__('Event aanmaken', 'bso-survival') . '</button> ';
        echo '<a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a></p>';
        echo '</form>';
        echo '<script>';
        echo '(function(){';
        echo 'var demo=document.getElementById("bso-create-demo-teams");';
        echo 'var count=document.getElementById("bso-demo-teams-count");';
        echo 'var parts=document.getElementById("bso-link-all-parts");';
        echo 'var scores=document.getElementById("bso-generate-scores");';
        echo 'var hint=document.getElementById("bso-generate-scores-hint");';
        echo 'if(!demo||!count||!parts||!scores){return;}';
        echo 'var sync=function(){';
        echo 'count.disabled=!demo.checked;';
        echo 'var ready=demo.checked&&parts.checked;';
        echo 'scores.disabled=!ready;';
        echo 'if(!ready){scores.checked=false;}';
        echo 'if(hint){hint.textContent=ready?"' . esc_js(__('Planning en score-records worden aangemaakt op basis van demo teams en gekoppelde onderdelen.', 'bso-survival')) . '":"' . esc_js(__('Vink eerst Demo teams aanmaken en Alle beschikbare onderdelen koppelen aan.', 'bso-survival')) . '";}';
        echo '};';
        echo 'demo.addEventListener("change",sync);';
        echo 'parts.addEventListener("change",sync);';
        echo 'sync();';
        echo '})();';
        echo '</script>';
    }

    /**
     * @param array<string, int> $summary
     */
    private function buildCreateSummaryMessage(array $summary): string {
        $parts = [__('Event aangemaakt.', 'bso-survival')];

        if ((int) ($summary['teams_created'] ?? 0) > 0) {
            $parts[] = sprintf(__('Demo teams: %d.', 'bso-survival'), (int) $summary['teams_created']);
        }
        if ((int) ($summary['parts_linked'] ?? 0) > 0) {
            $parts[] = sprintf(__('Onderdelen gekoppeld: %d.', 'bso-survival'), (int) $summary['parts_linked']);
        }
        if ((int) ($summary['part_rules_created'] ?? 0) > 0) {
            $parts[] = sprintf(__('Standaard scoreregels aangemaakt: %d.', 'bso-survival'), (int) $summary['part_rules_created']);
        }
        if ((int) ($summary['timeslots_created'] ?? 0) > 0) {
            $parts[] = sprintf(__('Timeslots: %d.', 'bso-survival'), (int) $summary['timeslots_created']);
        }
        if ((int) ($summary['assignments_created'] ?? 0) > 0) {
            $parts[] = sprintf(__('Assignments: %d.', 'bso-survival'), (int) $summary['assignments_created']);
        }
        if ((int) ($summary['scores_created'] ?? 0) > 0) {
            $parts[] = sprintf(__('Score-records: %d.', 'bso-survival'), (int) $summary['scores_created']);
        }

        return implode(' ', $parts);
    }

    /** @param object|null $selectedEvent */
    private function renderEditPanelForm($selectedEvent, int $selectedEventId, bool $isImmutable, string $closePanelUrl): void {
        if (!is_object($selectedEvent) || $selectedEventId <= 0) {
            echo '<p>' . esc_html__('Kies eerst een event om te bewerken.', 'bso-survival') . '</p>';
            echo '<p><a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a></p>';
            return;
        }

        $maxTeams = 22;
        $meta = json_decode((string) ($selectedEvent->meta_data ?? ''), true);
        if (is_array($meta) && isset($meta['max_teams'])) {
            $maxTeams = (int) $meta['max_teams'];
        }

        $registrationWindow = $this->registrationWindows->findByEventId($selectedEventId);
        $registrationWindowEnabled = is_object($registrationWindow) && (string) ($registrationWindow->status ?? '') === 'open';
        $registrationWindowOpensOn = $this->formatDateValue((string) ($registrationWindow->opens_at ?? ''));
        $registrationWindowClosesOn = $this->formatDateValue((string) ($registrationWindow->closes_at ?? ''));
        $eventDate = (string) ($selectedEvent->event_date ?? '');

        if ($registrationWindowOpensOn === '' || $registrationWindowClosesOn === '') {
            [$defaultOpensOn, $defaultClosesOn] = $this->defaultRegistrationWindowDates($eventDate);
            if ($registrationWindowOpensOn === '') {
                $registrationWindowOpensOn = $defaultOpensOn;
            }
            if ($registrationWindowClosesOn === '') {
                $registrationWindowClosesOn = $defaultClosesOn;
            }
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
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

        echo '<tr><th scope="row">' . esc_html__('Inschrijvingen', 'bso-survival') . '</th>';
        echo '<td>';
        echo '<label class="bso-switch">';
        echo '<input id="bso-registration-window-enabled" name="registration_window_enabled" type="checkbox" value="1"' . checked($registrationWindowEnabled, true, false) . ($isImmutable ? ' disabled="disabled"' : '') . ' />';
        echo '<span class="bso-switch-track" aria-hidden="true"><span class="bso-switch-thumb"></span></span>';
        echo '<span>' . esc_html__('Open voor inschrijvingen', 'bso-survival') . '</span>';
        echo '</label>';
        echo '<p class="description">' . esc_html__('Teams kunnen alleen inschrijven binnen deze periode. De sluitdatum moet vóór de eventdatum liggen.', 'bso-survival') . '</p>';
        echo '<p><label for="bso-registration-window-opens-on"><strong>' . esc_html__('Open vanaf', 'bso-survival') . '</strong></label><br />';
        echo '<input id="bso-registration-window-opens-on" name="registration_window_opens_on" type="date" value="' . esc_attr($registrationWindowOpensOn) . '"' . ($isImmutable ? ' disabled="disabled"' : '') . ' /></p>';
        echo '<p><label for="bso-registration-window-closes-on"><strong>' . esc_html__('Open tot en met', 'bso-survival') . '</strong></label><br />';
        echo '<input id="bso-registration-window-closes-on" name="registration_window_closes_on" type="date" value="' . esc_attr($registrationWindowClosesOn) . '"' . ($isImmutable ? ' disabled="disabled"' : '') . ' /></p>';
        echo '<p class="bso-window-hint">' . esc_html(sprintf(__('Eventdatum: %s. De inschrijvingsperiode hoort daarvóór te eindigen.', 'bso-survival'), $eventDate !== '' ? $eventDate : '-')) . '</p>';
        echo '</td></tr>';
        echo '</tbody></table>';

        echo '<p><button class="button button-primary"' . ($isImmutable ? ' disabled="disabled"' : '') . '>' . esc_html__('Event opslaan', 'bso-survival') . '</button> ';
        echo '<a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a></p>';
        echo '</form>';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function defaultRegistrationWindowDates(string $eventDate): array {
        if (!$this->isValidDateValue($eventDate)) {
            return ['', ''];
        }

        $eventMoment = \DateTimeImmutable::createFromFormat('Y-m-d', $eventDate, new \DateTimeZone('UTC'));
        if ($eventMoment === false) {
            return ['', ''];
        }

        $opensOn = $eventMoment->modify('-30 days')->format('Y-m-d');
        $closesOn = $eventMoment->modify('-1 day')->format('Y-m-d');

        return [$opensOn, $closesOn];
    }

    private function formatDateValue(string $dateTime): string {
        $value = trim($dateTime);
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return '';
        }

        return substr($value, 0, 10);
    }

    private function isValidDateValue(string $value): bool {
        if ($value === '') {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $value, new \DateTimeZone('UTC'));
        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    private function toUtcDateTime(string $date, bool $endOfDay = false): string {
        return $date . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
    }

    private function saveRegistrationWindow(int $eventId, string $eventDate, bool $enabled, string $opensOn, string $closesOn): bool {
        if ($eventId <= 0) {
            return false;
        }

        if (!$this->isValidDateValue($eventDate)) {
            throw new \InvalidArgumentException('event_date moet YYYY-MM-DD zijn.');
        }

        if (!$this->isValidDateValue($opensOn) || !$this->isValidDateValue($closesOn)) {
            throw new \InvalidArgumentException('De inschrijvingsperiode moet geldige datums bevatten.');
        }

        if (strcmp($opensOn, $closesOn) > 0) {
            throw new \InvalidArgumentException('De openingsdatum van de inschrijving mag niet na de sluitdatum liggen.');
        }

        if (strcmp($closesOn, $eventDate) >= 0) {
            throw new \InvalidArgumentException('De sluitdatum van de inschrijving moet vóór de eventdatum liggen.');
        }

        $windowStatus = $enabled ? 'open' : 'closed';
        $window = $this->registrationWindows->saveForEvent(
            $eventId,
            $this->toUtcDateTime($opensOn, false),
            $this->toUtcDateTime($closesOn, true),
            $windowStatus
        );

        return $window !== null;
    }

    private function renderPartSettingsPanelForm(int $selectedEventId, int $selectedPartId, bool $isImmutable, string $closePanelUrl, string $partFilter, string $partSortBy, string $partSortDirection, array $attachedLookup): void {
        if ($selectedEventId <= 0 || $selectedPartId <= 0) {
            echo '<p>' . esc_html__('Kies eerst een gekoppeld onderdeel in het grid.', 'bso-survival') . '</p>';
            echo '<p><a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a></p>';
            return;
        }

        if (!isset($attachedLookup[$selectedPartId])) {
            echo '<p>' . esc_html__('Dit onderdeel is niet geselecteerd voor dit event. Selecteer het onderdeel eerst via de checkbox en sla koppelingen op.', 'bso-survival') . '</p>';
            echo '<p><a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a></p>';
            return;
        }

        $rule = $this->partRules->findByPartId($selectedPartId);
        $mode = is_object($rule) && is_string($rule->scoring_mode ?? null) && $rule->scoring_mode !== ''
            ? (string) $rule->scoring_mode
            : 'points';
        $tiebreakerMode = is_object($rule) && is_string($rule->tiebreaker_mode ?? null) && $rule->tiebreaker_mode !== ''
            ? (string) $rule->tiebreaker_mode
            : 'manual_referee';
        $config = json_decode((string) (is_object($rule) ? ($rule->scoring_config ?? '') : ''), true);
        if (!is_array($config)) {
            $config = [];
        }

        $partName = '';
        $assignedParts = $this->admin->listAssignedPartsForEvent($selectedEventId);
        foreach ($assignedParts as $assignedPart) {
            if ((int) ($assignedPart->id ?? 0) !== $selectedPartId) {
                continue;
            }

            $partName = trim((string) ($assignedPart->name ?? ''));
            break;
        }
        if ($partName === '') {
            $partName = 'Part #' . $selectedPartId;
        }

        $disabled = $isImmutable ? ' disabled="disabled"' : '';
        $methods = ScoringMethodRegistry::all();

        echo '<p><strong>' . esc_html(sprintf(__('Part nr: #%d', 'bso-survival'), $selectedPartId)) . '</strong></p>';
        echo '<p><strong>' . esc_html__('Onderdeel', 'bso-survival') . ':</strong> ' . esc_html($partName) . '</p>';
        if ($isImmutable) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Dit event is read-only. Instellingen kunnen niet worden aangepast.', 'bso-survival') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_event_part_rule_save" />';
        echo '<input type="hidden" name="event_id" value="' . (int) $selectedEventId . '" />';
        echo '<input type="hidden" name="part_id" value="' . (int) $selectedPartId . '" />';
        echo '<input type="hidden" name="part_filter" value="' . esc_attr($partFilter) . '" />';
        echo '<input type="hidden" name="part_sort_by" value="' . esc_attr($partSortBy) . '" />';
        echo '<input type="hidden" name="part_sort_direction" value="' . esc_attr($partSortDirection) . '" />';
        wp_nonce_field(self::SAVE_PART_RULE_NONCE_ACTION, self::SAVE_PART_RULE_NONCE_FIELD);

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="bso-part-settings-scoring-mode">' . esc_html__('Scoring mode', 'bso-survival') . '</label></th>';
        echo '<td><select id="bso-part-settings-scoring-mode" name="scoring_mode"' . $disabled . '>';
        foreach ($methods as $id => $method) {
            echo '<option value="' . esc_attr((string) $id) . '" ' . selected($mode, (string) $id, false) . '>' . esc_html($method->getName()) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="bso-part-settings-tiebreaker">' . esc_html__('Tiebreaker', 'bso-survival') . '</label></th>';
        echo '<td><select id="bso-part-settings-tiebreaker" name="tiebreaker_mode"' . $disabled . '>';
        echo '<option value="manual_referee" ' . selected($tiebreakerMode, 'manual_referee', false) . '>manual_referee</option>';
        echo '<option value="lower_raw_wins" ' . selected($tiebreakerMode, 'lower_raw_wins', false) . '>lower_raw_wins</option>';
        echo '<option value="higher_raw_wins" ' . selected($tiebreakerMode, 'higher_raw_wins', false) . '>higher_raw_wins</option>';
        echo '</select></td></tr>';

        echo '<tr class="bso-part-settings-config" data-mode="time" style="display:' . ($mode === 'time' ? 'table-row' : 'none') . ';">';
        echo '<th scope="row"><label for="bso-part-settings-max-time"><strong>max_time</strong></label></th>';
        echo '<td><input id="bso-part-settings-max-time" type="number" min="1" name="max_time" value="' . esc_attr((string) ($config['max_time'] ?? 1200)) . '"' . $disabled . ' /></td>';
        echo '</tr>';

        echo '<tr class="bso-part-settings-config" data-mode="points" style="display:' . ($mode === 'points' ? 'table-row' : 'none') . ';">';
        echo '<th scope="row"><label for="bso-part-settings-max-points"><strong>max_points</strong></label></th>';
        echo '<td><input id="bso-part-settings-max-points" type="number" min="1" name="max_points" value="' . esc_attr((string) ($config['max_points'] ?? 100)) . '"' . $disabled . ' /></td>';
        echo '</tr>';

        echo '<tr class="bso-part-settings-config" data-mode="distance" style="display:' . ($mode === 'distance' ? 'table-row' : 'none') . ';">';
        echo '<th scope="row"><label for="bso-part-settings-max-distance"><strong>max_distance</strong></label></th>';
        echo '<td><input id="bso-part-settings-max-distance" type="number" min="1" name="max_distance" value="' . esc_attr((string) ($config['max_distance'] ?? 500)) . '"' . $disabled . ' /></td>';
        echo '</tr>';

        echo '<tr><th scope="row"><label for="bso-part-settings-curve"><strong>normalization_curve</strong></label></th>';
        echo '<td><select id="bso-part-settings-curve" name="normalization_curve"' . $disabled . '>';
        echo '<option value="linear" ' . selected((string) ($config['normalization_curve'] ?? 'linear'), 'linear', false) . '>linear</option>';
        echo '</select></td></tr>';
        echo '</tbody></table>';

        echo '<p><button class="button button-primary"' . $disabled . '>' . esc_html__('Opslaan', 'bso-survival') . '</button> ';
        echo '<a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a></p>';
        echo '</form>';

        echo '<script>';
        echo '(function(){var select=document.getElementById("bso-part-settings-scoring-mode");if(!select){return;}var form=select.closest("form");if(!form){return;}var sync=function(){var mode=select.value;form.querySelectorAll(".bso-part-settings-config").forEach(function(row){row.style.display=row.getAttribute("data-mode")===mode?"table-row":"none";});};select.addEventListener("change",sync);sync();})();';
        echo '</script>';
    }
}
