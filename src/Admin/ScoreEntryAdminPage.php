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
    private const INITIALIZE_NONCE_ACTION = 'bso_survival_admin_score_initialize';
    private const INITIALIZE_NONCE_FIELD = 'bso_survival_admin_score_initialize_nonce';

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
        $partId = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $teamId = isset($_POST['team_id']) ? (int) $_POST['team_id'] : 0;
        $selectedTeamId = isset($_POST['team_id_select']) ? (int) $_POST['team_id_select'] : 0;
        if ($selectedTeamId > 0) {
            $teamId = $selectedTeamId;
        }
        $sortBy = $this->normalizeSortBy(isset($_POST['sort_by']) ? sanitize_key((string) wp_unslash((string) $_POST['sort_by'])) : 'score_entry_id');
        $sortOrder = $this->normalizeSortOrder(isset($_POST['sort_order']) ? sanitize_key((string) wp_unslash((string) $_POST['sort_order'])) : 'desc');

        try {
            $result = $this->scores->create([
                'event_id' => $eventId,
                'assignment_id' => isset($_POST['assignment_id']) ? (int) $_POST['assignment_id'] : 0,
                'raw_value' => isset($_POST['raw_value']) ? (string) wp_unslash((string) $_POST['raw_value']) : '',
                'bonus_points' => isset($_POST['bonus_points']) ? (string) wp_unslash((string) $_POST['bonus_points']) : '0',
                'joker_applied' => isset($_POST['joker_applied']) ? (string) wp_unslash((string) $_POST['joker_applied']) : '0',
                'joker_validated_by' => isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin',
                'changed_by' => isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin',
                'entered_by_role' => 'admin',
            ]);

            $this->redirectWithStatus($eventId, 'created', '', [
                'part_id' => $partId,
                'team_id' => $teamId,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'auto_part_rules' => implode(',', array_map('intval', $result['auto_created_part_rule_ids'] ?? [])),
            ]);
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage(), [
                'part_id' => $partId,
                'team_id' => $teamId,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'panel' => 'create',
            ]);
        }
    }

    public function handleUpdate(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::UPDATE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::UPDATE_NONCE_FIELD], self::UPDATE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $scoreEntryId = isset($_POST['score_entry_id']) ? (int) $_POST['score_entry_id'] : 0;
        $selectedAssignmentId = isset($_POST['assignment_id_select']) ? (int) $_POST['assignment_id_select'] : 0;
        $partId = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;
        $teamId = isset($_POST['team_id']) ? (int) $_POST['team_id'] : 0;
        $sortBy = $this->normalizeSortBy(isset($_POST['sort_by']) ? sanitize_key((string) wp_unslash((string) $_POST['sort_by'])) : 'score_entry_id');
        $sortOrder = $this->normalizeSortOrder(isset($_POST['sort_order']) ? sanitize_key((string) wp_unslash((string) $_POST['sort_order'])) : 'desc');

        try {
            $result = $this->scores->update($scoreEntryId, [
                'event_id' => $eventId,
                'assignment_id' => $selectedAssignmentId,
                'raw_value' => isset($_POST['raw_value']) ? (string) wp_unslash((string) $_POST['raw_value']) : '',
                'bonus_points' => isset($_POST['bonus_points']) ? (string) wp_unslash((string) $_POST['bonus_points']) : '0',
                'joker_applied' => isset($_POST['joker_applied']) ? (string) wp_unslash((string) $_POST['joker_applied']) : '0',
                'joker_validated_by' => isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin',
                'changed_by' => isset($_POST['changed_by']) ? sanitize_text_field(wp_unslash((string) $_POST['changed_by'])) : 'admin',
                'entered_by_role' => 'admin',
            ]);

            $this->redirectWithStatus($eventId, 'updated', '', [
                'part_id' => $partId,
                'team_id' => $teamId,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'auto_part_rules' => implode(',', array_map('intval', $result['auto_created_part_rule_ids'] ?? [])),
            ]);
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage(), [
                'part_id' => $partId,
                'team_id' => $teamId,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'panel' => 'edit',
                'score_entry_id' => $scoreEntryId,
            ]);
        }
    }

    public function handleInitialize(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::INITIALIZE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::INITIALIZE_NONCE_FIELD], self::INITIALIZE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        try {
            $result = $this->scores->initializeForEvent($eventId, $this->defaultChangedBy());
            $message = sprintf(
                __('Initialisatie gereed: %d aangemaakt, %d overgeslagen (bestonden al), totaal assignments %d.', 'bso-survival'),
                (int) ($result['created_entries'] ?? 0),
                (int) ($result['skipped_existing'] ?? 0),
                (int) ($result['assignment_count'] ?? 0)
            );

            $this->redirectWithStatus($eventId, 'initialized', $message);
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

        $partId = isset($_GET['part_id']) ? (int) $_GET['part_id'] : 0;
        $teamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;
        $sortBy = $this->normalizeSortBy(isset($_GET['sort_by']) ? sanitize_key((string) wp_unslash((string) $_GET['sort_by'])) : 'score_entry_id');
        $sortOrder = $this->normalizeSortOrder(isset($_GET['sort_order']) ? sanitize_key((string) wp_unslash((string) $_GET['sort_order'])) : 'desc');
        $hasScoreFilter = $partId > 0 || $teamId > 0;

        $assignments = $eventId > 0 ? $this->assignments->findByEventId($eventId) : [];
        $filterOptions = $eventId > 0 ? $this->buildFilterOptions($assignments) : ['parts' => [], 'teams' => []];
        $scores = ($eventId > 0 && $hasScoreFilter) ? $this->listScoreRowsForEvent($eventId, $partId, $teamId, $sortBy, $sortOrder) : [];
        $panel = isset($_GET['panel']) ? sanitize_key((string) wp_unslash($_GET['panel'])) : '';
        if (!in_array($panel, ['create', 'edit'], true)) {
            $panel = '';
        }
        $selectedScoreEntryId = isset($_GET['score_entry_id']) ? (int) $_GET['score_entry_id'] : 0;
        $selectedScore = null;
        if ($panel === 'edit' && $selectedScoreEntryId > 0) {
            $selectedScore = $this->findScoreRowByEntryId($eventId, $selectedScoreEntryId);
            if ($selectedScore === null) {
                $panel = '';
            }
        }

        $newScoreUrl = $this->buildAdminUrl([
            'event_id' => $eventId,
            'part_id' => $partId,
            'team_id' => $teamId,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'panel' => 'create',
        ]);
        $closePanelUrl = $this->buildAdminUrl([
            'event_id' => $eventId,
            'part_id' => $partId,
            'team_id' => $teamId,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ]);
        $defaultChangedBy = $this->defaultChangedBy();
        $canManageSettings = Capabilities::canManageSettings();

        echo '<div class="wrap">';
        echo '<style>
            .bso-score-layout{position:relative;}
            .bso-score-main{max-width:100%;transition:margin-right .2s ease;}
            .bso-score-main.with-panel{margin-right:400px;}
            .bso-score-toolbar{margin:0 0 12px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
            .bso-score-header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
            .bso-score-role-chip{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;border:1px solid #d0d7de;background:#fff;color:#334155;font-size:12px;font-weight:600;}
            .bso-score-role-chip.is-admin{border-color:#1d4ed8;background:#eef2ff;color:#1e3a8a;}
            .bso-score-role-chip.is-operator{border-color:#0f766e;background:#ecfeff;color:#115e59;}
            .bso-score-role-note{margin:0 0 10px 0;color:#57606a;font-size:13px;}
            .bso-score-table td,.bso-score-table th{vertical-align:middle;}
            .bso-score-timeslot-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;border:1px solid #cbd5e1;background:#f8fafc;color:#334155;font-size:12px;font-weight:600;white-space:nowrap;}
            .bso-score-row-clickable{cursor:pointer;}
            .bso-score-row-clickable:hover td{background:#f6f7ff;}
            .bso-score-row-clickable:focus td{outline:2px solid #93c5fd;outline-offset:-2px;}
            .bso-score-row-clickable.is-selected td{background:#eef4ff;}
            .bso-score-row-clickable td:first-child{position:relative;padding-left:24px;}
            .bso-score-row-clickable td:first-child::before{content:"↗";position:absolute;left:8px;top:50%;transform:translateY(-50%);opacity:0;color:#64748b;transition:opacity .15s ease,color .15s ease;}
            .bso-score-row-clickable:hover td:first-child::before,
            .bso-score-row-clickable:focus td:first-child::before,
            .bso-score-row-clickable.is-selected td:first-child::before{opacity:1;color:#1d4ed8;}
            .bso-score-row-clickable.is-selected td:first-child{box-shadow:inset 4px 0 0 #1d4ed8;font-weight:600;}
            .bso-score-sort-link{text-decoration:none;display:inline-flex;align-items:center;gap:4px;}
            .bso-score-sort-arrow{font-size:12px;opacity:1;color:#9ca3af;line-height:1;min-width:10px;display:inline-block;}
            .bso-score-sort-link.is-active .bso-score-sort-arrow{color:#111827;}
            .bso-score-panel{position:fixed;top:32px;right:0;width:380px;height:calc(100vh - 32px);background:#fff;border-left:1px solid #dcdcde;z-index:999;padding:14px 16px 16px 16px;overflow:auto;box-shadow:-6px 0 20px rgba(0,0,0,.08);}
            .bso-score-panel-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:8px;}
            .bso-score-panel-title{font-size:20px;font-weight:600;margin:0;line-height:1.3;}
            .bso-score-context{margin:0 0 12px 0;padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;}
            @media (max-width: 1200px){
                .bso-score-main.with-panel{margin-right:0;}
                .bso-score-panel{position:static;width:auto;height:auto;box-shadow:none;border:1px solid #dcdcde;margin-top:14px;}
            }
        </style>';
        echo '<div class="bso-score-header">';
        echo '<h1>' . esc_html__('Score Invoer', 'bso-survival') . '</h1>';
        echo '<span class="bso-score-role-chip ' . ($canManageSettings ? 'is-admin' : 'is-operator') . '">' . esc_html($canManageSettings ? __('Volledige rechten', 'bso-survival') : __('Scorebeheer rechten', 'bso-survival')) . '</span>';
        echo '</div>';
        if (!$canManageSettings) {
            echo '<p class="bso-score-role-note">' . esc_html__('Je hebt scorebeheer-rechten. Event-setup acties zoals initialiseren zijn alleen beschikbaar voor gebruikers met volledige beheerrechten.', 'bso-survival') . '</p>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Score succesvol opgeslagen.', 'bso-survival') . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Score succesvol bijgewerkt.', 'bso-survival') . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'initialized') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : __('Scores geinitialiseerd.', 'bso-survival');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'error') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : __('Onbekende fout.', 'bso-survival');
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }

        if (isset($_GET['auto_part_rules']) && trim((string) $_GET['auto_part_rules']) !== '') {
            $rawIds = explode(',', sanitize_text_field(wp_unslash((string) $_GET['auto_part_rules'])));
            $partIds = array_values(array_filter(array_map('intval', $rawIds), static function (int $id): bool {
                return $id > 0;
            }));
            if ($partIds !== []) {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(sprintf(__('Voor onderdeel(en) %s ontbrak een scoreregel. Er is automatisch een standaardregel aangemaakt: points / linear / max_points 100.', 'bso-survival'), implode(', ', $partIds))) . '</p></div>';
            }
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="bso-score-toolbar">';
        echo '<input type="hidden" name="page" value="bso-survival-score-entry" />';
        echo '<input type="hidden" name="sort_by" value="' . esc_attr($sortBy) . '" />';
        echo '<input type="hidden" name="sort_order" value="' . esc_attr($sortOrder) . '" />';
        echo '<label for="bso-score-event-filter"><strong>' . esc_html__('Event', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-score-event-filter" name="event_id">';
        foreach ($events as $event) {
            $selected = selected($eventId, (int) $event->id, false);
            echo '<option value="' . (int) $event->id . '" ' . $selected . '>' . esc_html((string) $event->name) . '</option>';
        }
        echo '</select> ';
        echo '<label for="bso-score-part-filter"><strong>' . esc_html__('Onderdeel', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-score-part-filter" name="part_id">';
        echo '<option value="0">' . esc_html__('Kies onderdeel', 'bso-survival') . '</option>';
        foreach ($filterOptions['parts'] as $optionPartId => $optionPartName) {
            echo '<option value="' . (int) $optionPartId . '" ' . selected($partId, (int) $optionPartId, false) . '>' . esc_html($optionPartName) . '</option>';
        }
        echo '</select> ';
        echo '<label for="bso-score-team-filter"><strong>' . esc_html__('Team', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-score-team-filter" name="team_id">';
        echo '<option value="0">' . esc_html__('Kies team', 'bso-survival') . '</option>';
        foreach ($filterOptions['teams'] as $optionTeamId => $optionTeamName) {
            echo '<option value="' . (int) $optionTeamId . '" ' . selected($teamId, (int) $optionTeamId, false) . '>' . esc_html($optionTeamName) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button button-secondary">' . esc_html__('Laden', 'bso-survival') . '</button>';
        echo '<a class="button" href="' . esc_url($this->buildAdminUrl(['event_id' => $eventId])) . '">' . esc_html__('Reset filters', 'bso-survival') . '</a>';
        echo '</form>';
        echo '<p class="description" style="margin-top:6px;">' . esc_html__('Selecteer een event en minimaal een onderdeel of team. Beide extra filters tegelijk gebruiken mag ook.', 'bso-survival') . '</p>';

        if ($canManageSettings) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-left:8px;">';
            echo '<input type="hidden" name="action" value="bso_survival_admin_score_initialize" />';
            echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
            echo '<input type="hidden" name="sort_by" value="' . esc_attr($sortBy) . '" />';
            echo '<input type="hidden" name="sort_order" value="' . esc_attr($sortOrder) . '" />';
            wp_nonce_field(self::INITIALIZE_NONCE_ACTION, self::INITIALIZE_NONCE_FIELD);
            echo '<button class="button button-secondary" onclick="return confirm(\'' . esc_js(__('Initialiseer ontbrekende score-records voor alle assignments van dit event?', 'bso-survival')) . '\');">' . esc_html__('Initialiseer scores', 'bso-survival') . '</button>';
            echo '</form> ';
        }

        echo '<a class="button" href="' . esc_url($newScoreUrl) . '">' . esc_html__('Nieuwe score', 'bso-survival') . '</a>';

        echo '<div class="bso-score-layout">';
        echo '<div class="bso-score-main' . ($panel !== '' ? ' with-panel' : '') . '">';
        echo '<hr />';
        echo '<h2>' . esc_html__('Beschikbare scores', 'bso-survival') . '</h2>';

        if (!$hasScoreFilter) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Kies naast het event ten minste een onderdeel of een team om scores te laden.', 'bso-survival') . '</p></div>';
        } elseif ($scores === []) {
            echo '<p>' . esc_html__('Nog geen scores gevonden voor dit event.', 'bso-survival') . '</p>';
        } else {
            echo '<table class="widefat striped bso-score-table">';
            echo '<thead><tr>';
            $sortBaseArgs = [
                'event_id' => $eventId,
                'part_id' => $partId,
                'team_id' => $teamId,
            ];
            echo '<th>' . $this->renderSortableHeader(__('Score ID', 'bso-survival'), 'score_entry_id', $sortBy, $sortOrder, $sortBaseArgs) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Team', 'bso-survival'), 'team_name', $sortBy, $sortOrder, $sortBaseArgs) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Onderdeel', 'bso-survival'), 'part_name', $sortBy, $sortOrder, $sortBaseArgs) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Tijdsrange', 'bso-survival'), 'timeslot', $sortBy, $sortOrder, $sortBaseArgs) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Ruwe score', 'bso-survival'), 'raw_value', $sortBy, $sortOrder, $sortBaseArgs) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Bonus', 'bso-survival'), 'bonus_points', $sortBy, $sortOrder, $sortBaseArgs) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Joker', 'bso-survival'), 'joker_applied', $sortBy, $sortOrder, $sortBaseArgs) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Gewijzigd door', 'bso-survival'), 'changed_by', $sortBy, $sortOrder, $sortBaseArgs) . '</th>';
            echo '<th>' . $this->renderSortableHeader(__('Invoer tijd', 'bso-survival'), 'created_at', $sortBy, $sortOrder, $sortBaseArgs) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($scores as $score) {
                $scoreId = (int) ($score->score_entry_id ?? 0);
                $editUrl = $this->buildAdminUrl([
                    'event_id' => $eventId,
                    'part_id' => $partId,
                    'team_id' => $teamId,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                    'panel' => 'edit',
                    'score_entry_id' => $scoreId,
                ]);
                $rowClass = 'bso-score-row-clickable';
                if ($selectedScoreEntryId > 0 && $selectedScoreEntryId === $scoreId) {
                    $rowClass .= ' is-selected';
                }

                echo '<tr class="' . esc_attr($rowClass) . '" tabindex="0" role="button" aria-label="' . esc_attr(sprintf(__('Bewerk score #%d', 'bso-survival'), $scoreId)) . '" data-edit-url="' . esc_url($editUrl) . '">';
                echo '<td><a href="' . esc_url($editUrl) . '">#' . $scoreId . '</a></td>';
                echo '<td>' . esc_html((string) ($score->team_name ?? '-')) . '</td>';
                echo '<td>' . esc_html((string) ($score->part_name ?? '-')) . '</td>';
                echo '<td><span class="bso-score-timeslot-badge">' . esc_html($this->formatTimeslotRange((string) ($score->timeslot_start_at ?? ''), (string) ($score->timeslot_end_at ?? ''), (int) ($score->timeslot_id ?? 0))) . '</span></td>';
                echo '<td>' . esc_html((string) ($score->raw_value ?? '0')) . '</td>';
                echo '<td>' . esc_html((string) ($score->bonus_points ?? '0')) . '</td>';
                echo '<td>' . ((int) ($score->joker_applied ?? 0) === 1 ? esc_html__('Ja', 'bso-survival') : esc_html__('Nee', 'bso-survival')) . '</td>';
                echo '<td>' . esc_html((string) ($score->changed_by ?? '-')) . '</td>';
                echo '<td>' . esc_html((string) ($score->created_at ?? '-')) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        echo '<script>
            (function(){
                var rows = document.querySelectorAll(".bso-score-row-clickable");

                var openRow = function(row) {
                    var url = row.getAttribute("data-edit-url");
                    if (url) {
                        window.location.href = url;
                    }
                };

                rows.forEach(function(row){
                    row.addEventListener("click", function(event){
                        var target = event.target;
                        if (target && target.closest("a, button, input, select, textarea, label")) {
                            return;
                        }

                        openRow(row);
                    });

                    row.addEventListener("keydown", function(event){
                        if (event.key !== "Enter" && event.key !== " ") {
                            return;
                        }

                        event.preventDefault();
                        openRow(row);
                    });
                });
            })();
        </script>';

        }
        echo '</div>';

        if ($panel === 'create' || ($panel === 'edit' && $selectedScore !== null)) {
            echo '<aside class="bso-score-panel">';
            echo '<div class="bso-score-panel-top">';

            if ($panel === 'create') {
                echo '<h2 class="bso-score-panel-title">' . esc_html__('Nieuwe score toevoegen', 'bso-survival') . '</h2>';
            } else {
                $panelTitle = sprintf('%s, %s #%d', __('Bewerken score', 'bso-survival'), __('score ID', 'bso-survival'), (int) ($selectedScore->score_entry_id ?? 0));
                echo '<h2 class="bso-score-panel-title">' . esc_html($panelTitle) . '</h2>';
            }

            echo '<a class="button button-link" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a>';
            echo '</div>';

            if ($panel === 'create') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="bso_survival_admin_score_create" />';
                echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
                echo '<input type="hidden" name="part_id" value="' . (int) $partId . '" />';
                echo '<input type="hidden" name="team_id" value="' . (int) $teamId . '" />';
                echo '<input type="hidden" name="sort_by" value="' . esc_attr($sortBy) . '" />';
                echo '<input type="hidden" name="sort_order" value="' . esc_attr($sortOrder) . '" />';
                wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

                echo '<p><label for="bso-score-team-select-create"><strong>' . esc_html__('Team', 'bso-survival') . '</strong></label><br />';
                echo '<select id="bso-score-team-select-create" name="team_id_select" style="width:100%;max-width:100%;">';
                echo '<option value="0">' . esc_html__('Alle teams', 'bso-survival') . '</option>';
                foreach ($filterOptions['teams'] as $optionTeamId => $optionTeamName) {
                    echo '<option value="' . (int) $optionTeamId . '" ' . selected($teamId, (int) $optionTeamId, false) . '>' . esc_html($optionTeamName) . '</option>';
                }
                echo '</select></p>';

                echo '<p><label for="bso-score-assignment"><strong>' . esc_html__('Assignment', 'bso-survival') . '</strong></label><br />';
                echo '<select id="bso-score-assignment" name="assignment_id" required="required" style="width:100%;max-width:100%;">';
                echo '<option value="">' . esc_html__('Kies assignment', 'bso-survival') . '</option>';
                foreach ($assignments as $assignment) {
                    $label = sprintf('%s - %s - %s (#%d)', (string) ($assignment->team_name ?? ''), (string) ($assignment->part_name ?? ''), $this->formatTimeslotRange((string) ($assignment->timeslot_start_at ?? ''), (string) ($assignment->timeslot_end_at ?? ''), (int) ($assignment->timeslot_id ?? 0)), (int) ($assignment->id ?? 0));
                    echo '<option value="' . (int) ($assignment->id ?? 0) . '" data-team-id="' . (int) ($assignment->team_id ?? 0) . '">' . esc_html($label) . '</option>';
                }
                echo '</select></p>';

                echo '<p><label for="bso-score-raw-value"><strong>' . esc_html__('Ruwe score', 'bso-survival') . '</strong></label><br />';
                echo '<input id="bso-score-raw-value" type="number" step="0.01" name="raw_value" required="required" style="width:100%;max-width:100%;" /></p>';

                echo '<p><label for="bso-score-bonus-points"><strong>' . esc_html__('Bonus punten', 'bso-survival') . '</strong></label><br />';
                echo '<input id="bso-score-bonus-points" type="number" min="0" step="0.01" name="bonus_points" value="0" style="width:100%;max-width:100%;" /></p>';

                echo '<p><label><input type="checkbox" name="joker_applied" value="1" /> ' . esc_html__('Joker ingezet (score telt dubbel)', 'bso-survival') . '</label></p>';

                echo '<p><label for="bso-score-changed-by"><strong>' . esc_html__('Gewijzigd door', 'bso-survival') . '</strong></label><br />';
                echo '<input id="bso-score-changed-by" type="text" name="changed_by" value="' . esc_attr($defaultChangedBy) . '" style="width:100%;max-width:100%;" /></p>';

                echo '<p>';
                echo '<button class="button button-primary">' . esc_html__('Opslaan', 'bso-survival') . '</button> ';
                echo '<a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a>';
                echo '</p>';
                echo '</form>';
                echo '<script>
                    (function(){
                        var teamSelect = document.getElementById("bso-score-team-select-create");
                        var assignmentSelect = document.getElementById("bso-score-assignment");
                        if (!teamSelect || !assignmentSelect) {
                            return;
                        }

                        var originalOptions = Array.prototype.slice.call(assignmentSelect.options).map(function(option){
                            return option.cloneNode(true);
                        });

                        var applyFilter = function(){
                            var selectedTeamId = teamSelect.value || "0";
                            assignmentSelect.innerHTML = "";

                            originalOptions.forEach(function(option){
                                if (!option.value) {
                                    assignmentSelect.appendChild(option.cloneNode(true));
                                    return;
                                }

                                var optionTeamId = option.getAttribute("data-team-id") || "0";
                                if (selectedTeamId === "0" || optionTeamId === selectedTeamId) {
                                    assignmentSelect.appendChild(option.cloneNode(true));
                                }
                            });
                        };

                        teamSelect.addEventListener("change", applyFilter);
                        applyFilter();
                    })();
                </script>';
            } elseif ($selectedScore !== null) {
                $timeslotAssignmentOptions = $this->buildCompatibleAssignmentOptions($assignments, $selectedScore);

                echo '<div class="bso-score-context">';
                echo '<p><strong>' . esc_html__('Team', 'bso-survival') . ':</strong> ' . esc_html((string) ($selectedScore->team_name ?? '-')) . '</p>';
                echo '<p><strong>' . esc_html__('Onderdeel', 'bso-survival') . ':</strong> ' . esc_html((string) ($selectedScore->part_name ?? '-')) . '</p>';
                echo '<p><strong>' . esc_html__('Tijdsrange', 'bso-survival') . ':</strong> ' . esc_html($this->formatTimeslotRange((string) ($selectedScore->timeslot_start_at ?? ''), (string) ($selectedScore->timeslot_end_at ?? ''), (int) ($selectedScore->timeslot_id ?? 0))) . '</p>';
                echo '<p><strong>' . esc_html__('Assignment', 'bso-survival') . ':</strong> #' . (int) ($selectedScore->assignment_id ?? 0) . '</p>';
                echo '<p><strong>' . esc_html__('Bonus punten', 'bso-survival') . ':</strong> ' . esc_html((string) ($selectedScore->bonus_points ?? '0')) . '</p>';
                echo '<p><strong>' . esc_html__('Joker actief', 'bso-survival') . ':</strong> ' . (((int) ($selectedScore->joker_applied ?? 0) === 1) ? esc_html__('Ja', 'bso-survival') : esc_html__('Nee', 'bso-survival')) . '</p>';
                echo '</div>';

                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="bso_survival_admin_score_update" />';
                echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
                echo '<input type="hidden" name="part_id" value="' . (int) $partId . '" />';
                echo '<input type="hidden" name="team_id" value="' . (int) $teamId . '" />';
                echo '<input type="hidden" name="sort_by" value="' . esc_attr($sortBy) . '" />';
                echo '<input type="hidden" name="sort_order" value="' . esc_attr($sortOrder) . '" />';
                echo '<input type="hidden" name="score_entry_id" value="' . (int) ($selectedScore->score_entry_id ?? 0) . '" />';
                wp_nonce_field(self::UPDATE_NONCE_ACTION, self::UPDATE_NONCE_FIELD);

                echo '<p><label for="bso-score-assignment-update"><strong>' . esc_html__('Tijdsrange', 'bso-survival') . '</strong></label><br />';
                echo '<select id="bso-score-assignment-update" name="assignment_id_select" style="width:100%;max-width:100%;">';
                foreach ($timeslotAssignmentOptions as $option) {
                    $selected = selected((int) ($selectedScore->assignment_id ?? 0), (int) ($option['assignment_id'] ?? 0), false);
                    echo '<option value="' . (int) ($option['assignment_id'] ?? 0) . '" ' . $selected . '>' . esc_html((string) ($option['label'] ?? '')) . '</option>';
                }
                echo '</select></p>';

                echo '<p><label for="bso-score-raw-value-update"><strong>' . esc_html__('Nieuwe ruwe score', 'bso-survival') . '</strong></label><br />';
                echo '<input id="bso-score-raw-value-update" type="number" step="0.01" name="raw_value" required="required" value="' . esc_attr((string) ($selectedScore->raw_value ?? '')) . '" style="width:100%;max-width:100%;" /></p>';

                echo '<p><label for="bso-score-bonus-points-update"><strong>' . esc_html__('Bonus punten', 'bso-survival') . '</strong></label><br />';
                echo '<input id="bso-score-bonus-points-update" type="number" min="0" step="0.01" name="bonus_points" value="' . esc_attr((string) ($selectedScore->bonus_points ?? '0')) . '" style="width:100%;max-width:100%;" /></p>';

                echo '<p><label><input type="checkbox" name="joker_applied" value="1" ' . checked((int) ($selectedScore->joker_applied ?? 0), 1, false) . ' /> ' . esc_html__('Joker ingezet (score telt dubbel)', 'bso-survival') . '</label></p>';

                echo '<p><label for="bso-score-changed-by-update"><strong>' . esc_html__('Gewijzigd door', 'bso-survival') . '</strong></label><br />';
                echo '<input id="bso-score-changed-by-update" type="text" name="changed_by" value="' . esc_attr($defaultChangedBy) . '" style="width:100%;max-width:100%;" /></p>';

                echo '<p>';
                echo '<button class="button button-primary">' . esc_html__('Opslaan', 'bso-survival') . '</button> ';
                echo '<a class="button" href="' . esc_url($closePanelUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a>';
                echo '</p>';
                echo '</form>';
            }

            echo '</aside>';
        }

        echo '</div>';

        echo '</div>';
    }

    private function buildAdminUrl(array $overrides = []): string {
        $args = array_merge([
            'page' => 'bso-survival-score-entry',
        ], $overrides);

        return add_query_arg($args, admin_url('admin.php'));
    }

    private function listScoreRowsForEvent(int $eventId, int $partId = 0, int $teamId = 0, string $sortBy = 'score_entry_id', string $sortOrder = 'desc'): array {
        if ($eventId <= 0 || ($partId <= 0 && $teamId <= 0)) {
            return [];
        }

        global $wpdb;
        if (!is_object($wpdb)) {
            return [];
        }

        $scoreEntries = $wpdb->prefix . 'bso_survival_score_entries';
        $assignments = $wpdb->prefix . 'bso_survival_assignments';
        $timeslots = $wpdb->prefix . 'bso_survival_timeslots';
        $teams = $wpdb->prefix . 'bso_survival_teams';
        $parts = $wpdb->prefix . 'bso_survival_parts';

        $conditions = ['ts.event_id = %d'];
        $params = [$eventId];

        if ($partId > 0) {
            $conditions[] = 'a.part_id = %d';
            $params[] = $partId;
        }

        if ($teamId > 0) {
            $conditions[] = 'a.team_id = %d';
            $params[] = $teamId;
        }

        $sortExpression = $this->sortExpressionFor($sortBy);
        $direction = strtoupper($this->normalizeSortOrder($sortOrder));

        $sql = $wpdb->prepare(
            "SELECT se.id AS score_entry_id, se.assignment_id, a.part_id, a.team_id, se.raw_value, se.bonus_points, se.joker_applied, se.entered_by_role AS changed_by, se.created_at,
                    t.name AS team_name, p.name AS part_name, ts.id AS timeslot_id, ts.start_at AS timeslot_start_at, ts.end_at AS timeslot_end_at
             FROM {$scoreEntries} se
             INNER JOIN {$assignments} a ON a.id = se.assignment_id
             INNER JOIN {$timeslots} ts ON ts.id = a.timeslot_id
             INNER JOIN {$teams} t ON t.id = a.team_id
             INNER JOIN {$parts} p ON p.id = a.part_id
             WHERE " . implode(' AND ', $conditions) . "
             ORDER BY {$sortExpression} {$direction}, se.id DESC",
            ...$params
        );

        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * @param array<string, int> $baseArgs
     */
    private function renderSortableHeader(string $label, string $column, string $sortBy, string $sortOrder, array $baseArgs): string {
        $isCurrent = $sortBy === $column;
        $nextOrder = $isCurrent && $sortOrder === 'asc' ? 'desc' : 'asc';
        $arrow = '↕';

        if ($isCurrent) {
            $arrow = $sortOrder === 'asc' ? '▲' : '▼';
        }

        $url = $this->buildAdminUrl(array_merge($baseArgs, [
            'sort_by' => $column,
            'sort_order' => $nextOrder,
        ]));

        return '<a class="bso-score-sort-link' . ($isCurrent ? ' is-active' : '') . '" href="' . esc_url($url) . '">' .
            esc_html($label) .
            '<span class="bso-score-sort-arrow">' . esc_html($arrow) . '</span>' .
            '</a>';
    }

    private function normalizeSortBy(string $sortBy): string {
        $allowed = [
            'score_entry_id',
            'team_name',
            'part_name',
            'timeslot',
            'raw_value',
            'bonus_points',
            'joker_applied',
            'changed_by',
            'created_at',
        ];

        if (!in_array($sortBy, $allowed, true)) {
            return 'score_entry_id';
        }

        return $sortBy;
    }

    private function normalizeSortOrder(string $sortOrder): string {
        return $sortOrder === 'asc' ? 'asc' : 'desc';
    }

    private function sortExpressionFor(string $sortBy): string {
        switch ($this->normalizeSortBy($sortBy)) {
            case 'team_name':
                return 't.name';
            case 'part_name':
                return 'p.name';
            case 'timeslot':
                return 'ts.start_at';
            case 'raw_value':
                return 'se.raw_value';
            case 'bonus_points':
                return 'se.bonus_points';
            case 'joker_applied':
                return 'se.joker_applied';
            case 'changed_by':
                return 'se.entered_by_role';
            case 'created_at':
                return 'se.created_at';
            case 'score_entry_id':
            default:
                return 'se.id';
        }
    }

    /**
     * @param array<int, object> $assignments
     * @return array{parts: array<int, string>, teams: array<int, string>}
     */
    private function buildFilterOptions(array $assignments): array {
        $parts = [];
        $teams = [];

        foreach ($assignments as $assignment) {
            $partOptionId = (int) ($assignment->part_id ?? 0);
            if ($partOptionId > 0 && !isset($parts[$partOptionId])) {
                $parts[$partOptionId] = (string) ($assignment->part_name ?? ('#' . $partOptionId));
            }

            $teamOptionId = (int) ($assignment->team_id ?? 0);
            if ($teamOptionId > 0 && !isset($teams[$teamOptionId])) {
                $teams[$teamOptionId] = (string) ($assignment->team_name ?? ('#' . $teamOptionId));
            }
        }

        asort($parts);
        asort($teams);

        return [
            'parts' => $parts,
            'teams' => $teams,
        ];
    }

    private function findScoreRowByEntryId(int $eventId, int $scoreEntryId) {
        if ($eventId <= 0 || $scoreEntryId <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb)) {
            return null;
        }

        $scoreEntries = $wpdb->prefix . 'bso_survival_score_entries';
        $assignments = $wpdb->prefix . 'bso_survival_assignments';
        $timeslots = $wpdb->prefix . 'bso_survival_timeslots';
        $teams = $wpdb->prefix . 'bso_survival_teams';
        $parts = $wpdb->prefix . 'bso_survival_parts';

        $sql = $wpdb->prepare(
                "SELECT se.id AS score_entry_id, se.assignment_id, se.raw_value, se.bonus_points, se.joker_applied, se.entered_by_role AS changed_by, se.created_at,
                    t.name AS team_name, p.name AS part_name, ts.id AS timeslot_id, ts.start_at AS timeslot_start_at, ts.end_at AS timeslot_end_at
             FROM {$scoreEntries} se
             INNER JOIN {$assignments} a ON a.id = se.assignment_id
             INNER JOIN {$timeslots} ts ON ts.id = a.timeslot_id
             INNER JOIN {$teams} t ON t.id = a.team_id
             INNER JOIN {$parts} p ON p.id = a.part_id
             WHERE ts.event_id = %d AND se.id = %d
             LIMIT 1",
            $eventId,
            $scoreEntryId
        );

        return $wpdb->get_row($sql) ?: null;
    }

    private function defaultChangedBy(): string {
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if (is_object($user)) {
                $login = isset($user->user_login) ? (string) $user->user_login : '';
                if ($login !== '') {
                    return $login;
                }
            }
        }

        return 'admin';
    }

    private function formatTimeslotRange(string $startAt, string $endAt, int $timeslotId = 0): string {
        $startTs = $this->parseUtcDateTime($startAt);
        $endTs = $this->parseUtcDateTime($endAt);
        if ($startTs > 0 && $endTs > 0 && $endTs > $startTs) {
            return gmdate('H:i', $startTs) . ' - ' . gmdate('H:i', $endTs);
        }

        if ($timeslotId > 0) {
            return sprintf(__('Tijdslot %d', 'bso-survival'), $timeslotId);
        }

        return __('Geen tijdslot', 'bso-survival');
    }

    /**
     * @param array<int, object> $assignments
     * @return array<int, array<string, mixed>>
     */
    private function buildCompatibleAssignmentOptions(array $assignments, $selectedScore): array {
        $selectedPartId = (int) ($selectedScore->part_id ?? 0);
        $selectedTeamId = (int) ($selectedScore->team_id ?? 0);
        $selectedAssignmentId = (int) ($selectedScore->assignment_id ?? 0);
        $options = [];

        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment->id ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }

            if ((int) ($assignment->part_id ?? 0) !== $selectedPartId || (int) ($assignment->team_id ?? 0) !== $selectedTeamId) {
                continue;
            }

            $options[] = [
                'assignment_id' => $assignmentId,
                'timeslot_id' => (int) ($assignment->timeslot_id ?? 0),
                'label' => $this->formatTimeslotRange(
                    (string) ($assignment->timeslot_start_at ?? ''),
                    (string) ($assignment->timeslot_end_at ?? ''),
                    (int) ($assignment->timeslot_id ?? 0)
                ) . ' (#' . $assignmentId . ')',
            ];
        }

        usort($options, static function (array $left, array $right): int {
            $bySlot = ((int) ($left['timeslot_id'] ?? 0)) <=> ((int) ($right['timeslot_id'] ?? 0));
            if ($bySlot !== 0) {
                return $bySlot;
            }

            return ((int) ($left['assignment_id'] ?? 0)) <=> ((int) ($right['assignment_id'] ?? 0));
        });

        if ($options === [] && $selectedAssignmentId > 0) {
            $options[] = [
                'assignment_id' => $selectedAssignmentId,
                'timeslot_id' => 0,
                'label' => __('Huidige assignment', 'bso-survival') . ' (#' . $selectedAssignmentId . ')',
            ];
        }

        return $options;
    }

    private function parseUtcDateTime(string $value): int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        if ($date === false) {
            return 0;
        }

        return $date->getTimestamp();
    }

    private function assertAdminPermissions(): void {
        if (!Capabilities::canManageScores()) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }
    }

    /**
     * @param array<string, int|string> $extraArgs
     */
    private function redirectWithStatus(int $eventId, string $saved, string $message = '', array $extraArgs = []): void {
        $args = [
            'page' => 'bso-survival-score-entry',
            'event_id' => $eventId,
            'saved' => $saved,
        ];

        foreach ($extraArgs as $key => $value) {
            if ($value === '' || $value === 0 || $value === '0') {
                continue;
            }

            $args[$key] = $value;
        }

        if ($message !== '') {
            $args['message'] = $message;
        }

        $redirect = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
}
