<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\RegistrationWindowService;
use BSO\Survival\Service\TeamService;
use BSO\Survival\Support\Capabilities;
use RuntimeException;

class RegistrationAdminPage {
    private const UPDATE_TEAM_NONCE_ACTION = 'bso_survival_registration_team_update';
    private const UPDATE_TEAM_NONCE_FIELD = 'bso_survival_registration_team_update_nonce';

    /** @var EventService */
    private $events;

    /** @var TeamService */
    private $teams;

    /** @var RegistrationWindowService */
    private $windows;

    public function __construct(EventService $events, TeamService $teams, RegistrationWindowService $windows) {
        $this->events = $events;
        $this->teams = $teams;
        $this->windows = $windows;
    }

    public function registerMenu(): void {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            'bso-survival-rules',
            __('Inschrijvingen', 'bso-survival'),
            __('Inschrijvingen', 'bso-survival'),
            Capabilities::MANAGE_SETTINGS,
            'bso-survival-registrations',
            [$this, 'renderPage']
        );
    }

    public function handleTeamUpdate(): void {
        $this->assertAdminPermissions();

        if (!isset($_POST[self::UPDATE_TEAM_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::UPDATE_TEAM_NONCE_FIELD], self::UPDATE_TEAM_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $teamId = isset($_POST['team_id']) ? (int) $_POST['team_id'] : 0;
        $teamFilter = isset($_POST['team_filter']) ? sanitize_text_field(wp_unslash((string) $_POST['team_filter'])) : '';
        $teamSortBy = isset($_POST['team_sort_by']) ? sanitize_key((string) wp_unslash($_POST['team_sort_by'])) : 'team_name';
        $teamSortDirection = isset($_POST['team_sort_direction']) ? sanitize_key((string) wp_unslash($_POST['team_sort_direction'])) : 'asc';

        try {
            $team = $this->teams->getTeam($teamId);
            if (!is_object($team) || (int) ($team->event_id ?? 0) !== $eventId) {
                throw new RuntimeException(__('Team hoort niet bij het geselecteerde event.', 'bso-survival'));
            }

            $membersRaw = isset($_POST['team_members']) ? (string) wp_unslash($_POST['team_members']) : '';
            $members = $this->normalizeMembers($membersRaw);

            $updated = $this->teams->updateTeam(
                $teamId,
                isset($_POST['team_name']) ? sanitize_text_field(wp_unslash((string) $_POST['team_name'])) : '',
                isset($_POST['contact_name']) ? sanitize_text_field(wp_unslash((string) $_POST['contact_name'])) : '',
                isset($_POST['contact_email']) ? sanitize_email(wp_unslash((string) $_POST['contact_email'])) : '',
                isset($_POST['contact_phone']) ? sanitize_text_field(wp_unslash((string) $_POST['contact_phone'])) : '',
                isset($_POST['team_status']) ? sanitize_text_field(wp_unslash((string) $_POST['team_status'])) : '',
                $members
            );

            if ($updated === null) {
                throw new RuntimeException(__('Team kon niet worden bijgewerkt.', 'bso-survival'));
            }

            $this->redirectWithStatus($eventId, 'team_updated', '', $teamId, $teamFilter, $teamSortBy, $teamSortDirection, 'team');
        } catch (\Throwable $exception) {
            $this->redirectWithStatus($eventId, 'error', $exception->getMessage(), $teamId, $teamFilter, $teamSortBy, $teamSortDirection, 'team');
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

        $teamFilter = isset($_GET['team_filter']) ? sanitize_text_field(wp_unslash((string) $_GET['team_filter'])) : '';
        $teamSortBy = isset($_GET['team_sort_by']) ? sanitize_key((string) wp_unslash($_GET['team_sort_by'])) : 'team_name';
        $teamSortDirection = isset($_GET['team_sort_direction']) ? sanitize_key((string) wp_unslash($_GET['team_sort_direction'])) : 'asc';
        $eventPanel = isset($_GET['event_panel']) ? sanitize_key((string) wp_unslash($_GET['event_panel'])) : '';
        $selectedTeamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;

        if (!in_array($teamSortBy, ['team_name', 'contact_name', 'contact_email', 'contact_phone', 'team_status', 'members_count', 'created_at'], true)) {
            $teamSortBy = 'team_name';
        }
        if (!in_array($teamSortDirection, ['asc', 'desc'], true)) {
            $teamSortDirection = 'asc';
        }

        $teams = [];
        if ($selectedEventId > 0) {
            $teams = $this->teams->listTeamsForEvent($selectedEventId);
        }

        $teamRows = [];
        foreach ($teams as $team) {
            $teamId = (int) ($team->id ?? 0);
            $members = $teamId > 0 ? $this->teams->listMembersForTeam($teamId) : [];
            $memberNames = [];
            foreach ($members as $member) {
                $memberName = trim((string) ($member->name ?? ''));
                if ($memberName === '') {
                    continue;
                }

                $memberNames[] = $memberName;
            }

            $searchBlob = strtolower(implode(' ', [
                (string) ($team->name ?? ''),
                (string) ($team->contact_name ?? ''),
                (string) ($team->contact_email ?? ''),
                (string) ($team->contact_phone ?? ''),
                (string) ($team->status ?? ''),
                implode(' ', $memberNames),
            ]));

            if ($teamFilter !== '' && strpos($searchBlob, strtolower($teamFilter)) === false) {
                continue;
            }

            $teamRows[] = [
                'team' => $team,
                'team_id' => $teamId,
                'members' => $memberNames,
                'members_count' => count($memberNames),
            ];
        }

        usort($teamRows, function (array $left, array $right) use ($teamSortBy, $teamSortDirection): int {
            $leftTeam = $left['team'];
            $rightTeam = $right['team'];

            if ($teamSortBy === 'members_count') {
                $cmp = ((int) $left['members_count']) <=> ((int) $right['members_count']);
            } else {
                $leftValue = '';
                $rightValue = '';

                if ($teamSortBy === 'team_name') {
                    $leftValue = (string) ($leftTeam->name ?? '');
                    $rightValue = (string) ($rightTeam->name ?? '');
                } elseif ($teamSortBy === 'contact_name') {
                    $leftValue = (string) ($leftTeam->contact_name ?? '');
                    $rightValue = (string) ($rightTeam->contact_name ?? '');
                } elseif ($teamSortBy === 'contact_email') {
                    $leftValue = (string) ($leftTeam->contact_email ?? '');
                    $rightValue = (string) ($rightTeam->contact_email ?? '');
                } elseif ($teamSortBy === 'contact_phone') {
                    $leftValue = (string) ($leftTeam->contact_phone ?? '');
                    $rightValue = (string) ($rightTeam->contact_phone ?? '');
                } elseif ($teamSortBy === 'team_status') {
                    $leftValue = (string) ($leftTeam->status ?? '');
                    $rightValue = (string) ($rightTeam->status ?? '');
                } else {
                    $leftValue = (string) ($leftTeam->created_at ?? '');
                    $rightValue = (string) ($rightTeam->created_at ?? '');
                }

                $cmp = strcmp(strtolower($leftValue), strtolower($rightValue));
            }

            if ($cmp === 0) {
                $cmp = ((int) ($leftTeam->id ?? 0)) <=> ((int) ($rightTeam->id ?? 0));
            }

            return $teamSortDirection === 'desc' ? ($cmp * -1) : $cmp;
        });

        $selectedTeam = null;
        foreach ($teamRows as $row) {
            if ((int) $row['team_id'] === $selectedTeamId) {
                $selectedTeam = $row;
                break;
            }
        }

        echo '<div class="wrap">';
        echo '<style>
            .bso-registration-layout{position:relative;}
            .bso-registration-main{max-width:100%;transition:margin-right .2s ease;}
            .bso-registration-main.with-panel{margin-right:420px;}
            .bso-registration-panel{position:fixed;top:32px;right:0;width:400px;height:calc(100vh - 32px);background:#fff;border-left:1px solid #dcdcde;z-index:999;padding:14px 16px 16px 16px;overflow:auto;box-shadow:-6px 0 20px rgba(0,0,0,.08);}
            .bso-registration-panel-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:8px;}
            .bso-registration-panel-title{font-size:20px;font-weight:600;margin:0;}
            .bso-registration-toolbar{display:flex;justify-content:flex-start;align-items:center;gap:12px;flex-wrap:wrap;margin:10px 0 14px 0;}
            .bso-registration-toolbar form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0;}
            .bso-registration-filter-button,.bso-registration-reset-button{display:inline-flex;justify-content:center;align-items:center;min-width:82px;text-align:center;}
            .bso-registration-phone-links a{display:inline-block;margin-right:6px;}
            .bso-registration-members{max-width:320px;white-space:normal;line-height:1.4;}
            @media (max-width: 1280px){
                .bso-registration-main.with-panel{margin-right:0;}
                .bso-registration-panel{position:static;width:auto;height:auto;box-shadow:none;border:1px solid #dcdcde;margin-top:14px;}
            }
        </style>';
        echo '<h1>' . esc_html__('Inschrijvingsbeheer', 'bso-survival') . '</h1>';
        echo '<p>' . esc_html__('Kies een event en beheer de ingeschreven teams.', 'bso-survival') . '</p>';

        if ($events === []) {
            echo '<p>' . esc_html__('Geen events gevonden.', 'bso-survival') . '</p>';
            echo '</div>';
            return;
        }

        if (isset($_GET['saved']) && $_GET['saved'] === 'team_updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Team bijgewerkt.', 'bso-survival') . '</p></div>';
        }
        if (isset($_GET['saved']) && $_GET['saved'] === 'error') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash((string) $_GET['message'])) : __('Onbekende fout.', 'bso-survival');
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }

        $windowOpen = $selectedEvent !== null ? $this->windows->isOpenForEvent($selectedEventId) : false;
        $registered = $selectedEventId > 0 ? $this->teams->countTeamsForEvent($selectedEventId) : 0;
        $maxTeams = $selectedEvent !== null ? $this->extractMaxTeams((string) ($selectedEvent->meta_data ?? '')) : 0;
        $occupancy = $maxTeams > 0 ? min(100, (int) round(($registered / $maxTeams) * 100)) : 0;

        echo '<div class="bso-registration-layout">';
        echo '<div class="bso-registration-main' . ($eventPanel === 'team' ? ' with-panel' : '') . '">';

        echo '<div class="notice inline" style="display:block;padding:10px 12px;max-width:1120px;">';
        echo '<p><strong>' . esc_html__('Event', 'bso-survival') . ':</strong> ' . esc_html($selectedEvent !== null ? sprintf('#%d %s', $selectedEventId, (string) ($selectedEvent->name ?? '')) : __('niet geselecteerd', 'bso-survival')) . '</p>';
        echo '<p><strong>' . esc_html__('Status', 'bso-survival') . ':</strong> ' . esc_html((string) ($selectedEvent->status ?? 'onbekend')) . ' · <strong>' . esc_html__('Venster', 'bso-survival') . ':</strong> ' . esc_html($windowOpen ? __('open', 'bso-survival') : __('gesloten', 'bso-survival')) . ' · <strong>' . esc_html__('Inschrijving', 'bso-survival') . ':</strong> ' . esc_html($maxTeams > 0 ? sprintf('%d / %d (%d%%)', $registered, $maxTeams, $occupancy) : sprintf('%d / ?', $registered));
        echo '</p>';
        echo '</div>';

        echo '<div class="bso-registration-toolbar">';
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-survival-registrations" />';
        echo '<input type="hidden" name="team_sort_by" value="' . esc_attr($teamSortBy) . '" />';
        echo '<input type="hidden" name="team_sort_direction" value="' . esc_attr($teamSortDirection) . '" />';
        if ($teamFilter !== '') {
            echo '<input type="hidden" name="team_filter" value="' . esc_attr($teamFilter) . '" />';
        }
        echo '<label for="bso-registrations-event-id"><strong>' . esc_html__('Event', 'bso-survival') . ':</strong></label>';
        echo '<select id="bso-registrations-event-id" name="event_id">';
        foreach ($events as $event) {
            $eventId = (int) ($event->id ?? 0);
            $label = sprintf('#%d %s (%s)', $eventId, (string) ($event->name ?? ''), (string) ($event->status ?? 'onbekend'));
            echo '<option value="' . $eventId . '" ' . selected($selectedEventId, $eventId, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<button class="button">' . esc_html__('Laden', 'bso-survival') . '</button>';
        echo '</form>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-survival-registrations" />';
        echo '<input type="hidden" name="event_id" value="' . (int) $selectedEventId . '" />';
        echo '<input type="hidden" name="team_sort_by" value="' . esc_attr($teamSortBy) . '" />';
        echo '<input type="hidden" name="team_sort_direction" value="' . esc_attr($teamSortDirection) . '" />';
        echo '<input type="search" name="team_filter" class="regular-text" placeholder="' . esc_attr__('Filter teams/contact', 'bso-survival') . '" value="' . esc_attr($teamFilter) . '" />';
        echo '<button class="button bso-registration-filter-button">' . esc_html__('Filter', 'bso-survival') . '</button>';
        $resetUrl = $this->buildAdminUrl([
            'event_id' => $selectedEventId,
            'team_sort_by' => $teamSortBy,
            'team_sort_direction' => $teamSortDirection,
        ]);
        echo '<a class="button bso-registration-reset-button" href="' . esc_url($resetUrl) . '">' . esc_html__('Reset', 'bso-survival') . '</a>';
        echo '</form>';
        echo '</div>';

        echo '<table class="widefat striped" style="max-width:1120px;">';
        echo '<thead><tr>';
        echo '<th>' . $this->renderSortLink('team_name', __('Team', 'bso-survival'), $selectedEventId, $teamFilter, $teamSortBy, $teamSortDirection) . '</th>';
        echo '<th>' . $this->renderSortLink('contact_name', __('Contactpersoon', 'bso-survival'), $selectedEventId, $teamFilter, $teamSortBy, $teamSortDirection) . '</th>';
        echo '<th>' . $this->renderSortLink('contact_email', __('E-mail', 'bso-survival'), $selectedEventId, $teamFilter, $teamSortBy, $teamSortDirection) . '</th>';
        echo '<th>' . $this->renderSortLink('contact_phone', __('Mobiel', 'bso-survival'), $selectedEventId, $teamFilter, $teamSortBy, $teamSortDirection) . '</th>';
        echo '<th>' . esc_html__('Teamleden', 'bso-survival') . '</th>';
        echo '<th>' . $this->renderSortLink('members_count', __('Aantal teamleden', 'bso-survival'), $selectedEventId, $teamFilter, $teamSortBy, $teamSortDirection) . '</th>';
        echo '<th>' . $this->renderSortLink('team_status', __('Status', 'bso-survival'), $selectedEventId, $teamFilter, $teamSortBy, $teamSortDirection) . '</th>';
        echo '<th>' . $this->renderSortLink('created_at', __('Aangemaakt', 'bso-survival'), $selectedEventId, $teamFilter, $teamSortBy, $teamSortDirection) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($teamRows as $row) {
            $team = $row['team'];
            $teamId = (int) $row['team_id'];
            $teamName = (string) ($team->name ?? '');
            $contactName = (string) ($team->contact_name ?? '');
            $email = (string) ($team->contact_email ?? '');
            $phone = (string) ($team->contact_phone ?? '');
            $teamPanelUrl = $this->buildAdminUrl([
                'event_id' => $selectedEventId,
                'event_panel' => 'team',
                'team_id' => $teamId,
                'team_filter' => $teamFilter,
                'team_sort_by' => $teamSortBy,
                'team_sort_direction' => $teamSortDirection,
            ]);
            echo '<tr>';
            echo '<td><a href="' . esc_url($teamPanelUrl) . '">' . esc_html($teamName !== '' ? $teamName : ('#' . $teamId)) . '</a></td>';
            echo '<td>' . ($email !== '' ? '<a href="' . esc_url('mailto:' . $email) . '">' . esc_html($contactName) . '</a>' : esc_html($contactName)) . '</td>';
            echo '<td>' . ($email !== '' ? '<a href="' . esc_url('mailto:' . $email) . '">' . esc_html($email) . '</a>' : '-') . '</td>';
            echo '<td class="bso-registration-phone-links">' . $this->renderPhoneLinks($phone) . '</td>';
            echo '<td class="bso-registration-members">' . esc_html(implode(', ', $row['members'])) . '</td>';
            echo '<td>' . (int) $row['members_count'] . '</td>';
                echo '<td>' . esc_html((string) ($team->status ?? 'ingeschreven')) . '</td>';
            echo '<td>' . esc_html((string) ($team->created_at ?? '-')) . '</td>';
            echo '</tr>';
        }

        if ($teamRows === []) {
            echo '<tr><td colspan="8">' . esc_html__('Geen teams gevonden voor dit event/filter.', 'bso-survival') . '</td></tr>';
        }

        echo '</tbody></table>';

        echo '</div>';

        if ($eventPanel === 'team') {
            $this->renderTeamSidePanel($selectedTeam, $selectedEventId, $teamFilter, $teamSortBy, $teamSortDirection);
        }

        echo '</div>';

        echo '<script>';
        echo '(function(){var links=document.querySelectorAll(".bso-phone-link");if(!links.length){return;}var ua=(navigator.userAgent||"").toLowerCase();var mobile=/android|iphone|ipad|ipod|windows phone/.test(ua);links.forEach(function(link){var tel=link.getAttribute("data-tel")||"";var wa=link.getAttribute("data-wa")||"";if(mobile&&tel){link.setAttribute("href",tel);link.removeAttribute("target");}else if(wa){link.setAttribute("href",wa);link.setAttribute("target","_blank");link.setAttribute("rel","noopener noreferrer");}});})();';
        echo '</script>';

        echo '</div>';
    }

    private function renderTeamSidePanel(?array $selectedTeam, int $eventId, string $teamFilter, string $teamSortBy, string $teamSortDirection): void {
        $closeUrl = $this->buildAdminUrl([
            'event_id' => $eventId,
            'team_filter' => $teamFilter,
            'team_sort_by' => $teamSortBy,
            'team_sort_direction' => $teamSortDirection,
        ]);

        if ($selectedTeam === null) {
            echo '<aside class="bso-registration-panel">';
            echo '<div class="bso-registration-panel-top">';
            echo '<p class="bso-registration-panel-title">' . esc_html__('Teamgegevens bewerken', 'bso-survival') . '</p>';
            echo '<a class="button button-link" href="' . esc_url($closeUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a>';
            echo '</div>';
            echo '<p>' . esc_html__('Kies een team uit het overzicht.', 'bso-survival') . '</p>';
            echo '</aside>';
            return;
        }

        $team = $selectedTeam['team'];
        $teamId = (int) $selectedTeam['team_id'];
        $members = (array) $selectedTeam['members'];
        $membersText = implode("\n", $members);

        echo '<aside class="bso-registration-panel">';
        echo '<div class="bso-registration-panel-top">';
        echo '<p class="bso-registration-panel-title">' . esc_html__('Teamgegevens bewerken', 'bso-survival') . '</p>';
        echo '<a class="button button-link" href="' . esc_url($closeUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a>';
        echo '</div>';
        echo '<p><strong>' . esc_html__('Team ID', 'bso-survival') . ':</strong> #' . (int) $teamId . '</p>';

        $knownStatuses = ['ingeschreven', 'bevestigd', 'afgemeld'];
        $currentStatus = (string) ($team->status ?? 'ingeschreven');
        if (!in_array($currentStatus, $knownStatuses, true)) {
            $knownStatuses[] = $currentStatus;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_registration_team_update" />';
        echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
        echo '<input type="hidden" name="team_id" value="' . (int) $teamId . '" />';
        echo '<input type="hidden" name="team_filter" value="' . esc_attr($teamFilter) . '" />';
        echo '<input type="hidden" name="team_sort_by" value="' . esc_attr($teamSortBy) . '" />';
        echo '<input type="hidden" name="team_sort_direction" value="' . esc_attr($teamSortDirection) . '" />';
        wp_nonce_field(self::UPDATE_TEAM_NONCE_ACTION, self::UPDATE_TEAM_NONCE_FIELD);

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="bso-reg-team-name">' . esc_html__('Team', 'bso-survival') . '</label></th><td><input id="bso-reg-team-name" name="team_name" type="text" class="regular-text" value="' . esc_attr((string) ($team->name ?? '')) . '" required="required" /></td></tr>';
        echo '<tr><th scope="row"><label for="bso-reg-contact-name">' . esc_html__('Contactpersoon', 'bso-survival') . '</label></th><td><input id="bso-reg-contact-name" name="contact_name" type="text" class="regular-text" value="' . esc_attr((string) ($team->contact_name ?? '')) . '" required="required" /></td></tr>';
        echo '<tr><th scope="row"><label for="bso-reg-contact-email">' . esc_html__('E-mail', 'bso-survival') . '</label></th><td><input id="bso-reg-contact-email" name="contact_email" type="email" class="regular-text" value="' . esc_attr((string) ($team->contact_email ?? '')) . '" required="required" /></td></tr>';
        echo '<tr><th scope="row"><label for="bso-reg-contact-phone">' . esc_html__('Mobiel', 'bso-survival') . '</label></th><td><input id="bso-reg-contact-phone" name="contact_phone" type="text" class="regular-text" value="' . esc_attr((string) ($team->contact_phone ?? '')) . '" required="required" /></td></tr>';
        echo '<tr><th scope="row"><label for="bso-reg-team-status">' . esc_html__('Status', 'bso-survival') . '</label></th><td><select id="bso-reg-team-status" name="team_status">';
        foreach ($knownStatuses as $status) {
            echo '<option value="' . esc_attr($status) . '" ' . selected($currentStatus, $status, false) . '>' . esc_html($status) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="bso-reg-team-members">' . esc_html__('Teamleden', 'bso-survival') . '</label></th><td><textarea id="bso-reg-team-members" name="team_members" rows="8" class="large-text code">' . esc_textarea($membersText) . '</textarea><p class="description">' . esc_html__('Een teamlid per regel.', 'bso-survival') . '</p></td></tr>';
        echo '</tbody></table>';

        echo '<p><button class="button button-primary">' . esc_html__('Opslaan', 'bso-survival') . '</button> ';
        echo '<a class="button" href="' . esc_url($closeUrl) . '">' . esc_html__('Annuleren', 'bso-survival') . '</a></p>';
        echo '</form>';
        echo '</aside>';
    }

    private function renderSortLink(string $column, string $label, int $eventId, string $teamFilter, string $currentSortBy, string $currentSortDirection): string {
        $isActive = $column === $currentSortBy;
        $nextDirection = $isActive && $currentSortDirection === 'asc' ? 'desc' : 'asc';
        $indicator = '';
        if ($isActive) {
            $indicator = $currentSortDirection === 'asc' ? ' ↑' : ' ↓';
        }

        $url = $this->buildAdminUrl([
            'event_id' => $eventId,
            'team_filter' => $teamFilter,
            'team_sort_by' => $column,
            'team_sort_direction' => $nextDirection,
        ]);

        return '<a href="' . esc_url($url) . '">' . esc_html($label . $indicator) . '</a>';
    }

    private function renderPhoneLinks(string $phone): string {
        $raw = trim($phone);
        if ($raw === '') {
            return '-';
        }

        $tel = $this->toTel($raw);
        $wa = $this->toWhatsapp($raw);

        $link = '<a class="bso-phone-link" data-tel="' . esc_attr($tel) . '" data-wa="' . esc_attr($wa) . '" href="' . esc_url($wa) . '">' . esc_html($raw) . '</a>';
        return $link;
    }

    private function toTel(string $phone): string {
        $normalized = preg_replace('/[^0-9+]/', '', $phone);
        return 'tel:' . ($normalized ?? $phone);
    }

    private function toWhatsapp(string $phone): string {
        $digits = preg_replace('/\D+/', '', $phone);
        return 'https://wa.me/' . ($digits ?? '');
    }

    private function buildAdminUrl(array $args): string {
        $baseArgs = ['page' => 'bso-survival-registrations'];
        return add_query_arg(array_merge($baseArgs, $args), admin_url('admin.php'));
    }

    private function redirectWithStatus(int $eventId, string $saved, string $message = '', int $teamId = 0, string $teamFilter = '', string $teamSortBy = 'team_name', string $teamSortDirection = 'asc', string $eventPanel = ''): void {
        $args = [
            'page' => 'bso-survival-registrations',
            'event_id' => $eventId,
            'saved' => $saved,
            'team_sort_by' => $teamSortBy,
            'team_sort_direction' => $teamSortDirection,
        ];

        if ($message !== '') {
            $args['message'] = $message;
        }
        if ($teamId > 0) {
            $args['team_id'] = $teamId;
        }
        if ($teamFilter !== '') {
            $args['team_filter'] = $teamFilter;
        }
        if ($eventPanel !== '') {
            $args['event_panel'] = $eventPanel;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeMembers(string $membersRaw): array {
        $lines = preg_split('/\r\n|\r|\n/', $membersRaw);
        if (!is_array($lines)) {
            return [];
        }

        $members = [];
        foreach ($lines as $line) {
            $name = sanitize_text_field((string) $line);
            if ($name === '') {
                continue;
            }

            $members[] = $name;
        }

        return array_values(array_unique($members));
    }

    private function assertAdminPermissions(): void {
        if (!Capabilities::canManageSettings()) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }
    }

    private function extractMaxTeams(string $metaData): int {
        if ($metaData === '') {
            return 0;
        }

        $decoded = json_decode($metaData, true);
        if (!is_array($decoded)) {
            return 0;
        }

        $maxTeams = (int) ($decoded['max_teams'] ?? 0);
        return $maxTeams > 0 ? $maxTeams : 0;
    }
}
