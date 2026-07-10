<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\EventPublicationService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Support\Capabilities;

class EventLifecycleAdminPage {
    /** @var EventService */
    private $events;

    /** @var EventPublicationService */
    private $publications;

    public function __construct(EventService $events, EventPublicationService $publications) {
        $this->events = $events;
        $this->publications = $publications;
    }

    public function registerMenu(): void {
        if (function_exists('add_submenu_page')) {
            add_submenu_page(
                'bso-survival-rules',
                __('Event Lifecycle', 'bso-survival'),
                __('Event Lifecycle', 'bso-survival'),
                Capabilities::MANAGE_SETTINGS,
                'bso-survival-event-lifecycle',
                [$this, 'renderPage']
            );
            return;
        }

        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            __('Event Lifecycle', 'bso-survival'),
            __('Event Lifecycle', 'bso-survival'),
            Capabilities::MANAGE_SETTINGS,
            'bso-survival-event-lifecycle',
            [$this, 'renderPage'],
            'dashicons-update',
            60
        );
    }

    public function renderPage(): void {
        if (!Capabilities::canManageSettings()) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }

        wp_enqueue_script('bso-survival-admin-event-lifecycle');

        $events = $this->events->listEvents();
        $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
        if ($eventId <= 0 && !empty($events)) {
            $eventId = (int) $events[0]->id;
        }

        $publication = null;
        if ($eventId > 0) {
            $publication = $this->publications->getForEvent($eventId);
        }

        $selectedEvent = null;
        foreach ($events as $event) {
            if ((int) $event->id === $eventId) {
                $selectedEvent = $event;
                break;
            }
        }

        $restBase = function_exists('rest_url')
            ? (string) rest_url('bso-survival/v1/event-closeout')
            : '';
        $restNonce = function_exists('wp_create_nonce')
            ? (string) wp_create_nonce('wp_rest')
            : '';

        $defaultChangedBy = $this->defaultChangedBy();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('BSO Survival Event Lifecycle', 'bso-survival') . '</h1>';
        echo '<p>' . esc_html__('Sluit events af en publiceer de eindstand via de bestaande REST-routes.', 'bso-survival') . '</p>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-survival-event-lifecycle" />';
        echo '<label for="bso-lifecycle-event-id"><strong>' . esc_html__('Event', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-lifecycle-event-id" name="event_id">';
        foreach ($events as $event) {
            $selected = selected($eventId, (int) $event->id, false);
            echo '<option value="' . (int) $event->id . '" ' . $selected . '>' . esc_html($event->name) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button">' . esc_html__('Laden', 'bso-survival') . '</button>';
        echo '</form>';

        if ($eventId <= 0) {
            echo '<p>' . esc_html__('Geen event beschikbaar.', 'bso-survival') . '</p>';
            echo '</div>';
            return;
        }

        echo '<hr />';
        $eventName = $selectedEvent !== null ? (string) ($selectedEvent->name ?? '') : '';
        $eventStatus = $selectedEvent !== null ? (string) ($selectedEvent->status ?? '') : '';
        echo '<div id="bso-event-lifecycle-admin" data-event-id="' . (int) $eventId . '" data-event-name="' . esc_attr($eventName) . '" data-event-status="' . esc_attr($eventStatus) . '" data-rest-base="' . esc_attr(rtrim($restBase, '/')) . '" data-rest-nonce="' . esc_attr($restNonce) . '">';

        echo '<div class="notice inline" style="display:block;padding:10px 12px;">';
        echo '<p><strong>' . esc_html__('Actief event', 'bso-survival') . ':</strong> ' . esc_html($eventName !== '' ? $eventName : ('#' . (int) $eventId)) . '</p>';
        echo '<p><strong>' . esc_html__('Huidige status', 'bso-survival') . ':</strong> ' . esc_html($eventStatus !== '' ? $eventStatus : __('onbekend', 'bso-survival')) . '</p>';
        echo '</div>';

        $publicationJson = function_exists('wp_json_encode')
            ? (string) wp_json_encode($publication)
            : (string) json_encode($publication);

        echo '<h2>' . esc_html__('Persisted eindstand (bron van waarheid)', 'bso-survival') . '</h2>';
        echo '<div class="notice inline" style="display:block;padding:10px 12px;">';
        echo '<p><button type="button" id="bso-lifecycle-refresh-persisted" class="button button-secondary">' . esc_html__('Refresh persisted result', 'bso-survival') . '</button></p>';

        if (!is_array($publication) || $publication === []) {
            echo '<p id="bso-lifecycle-persisted-empty">' . esc_html__('Nog geen persisted publicatieresultaat gevonden voor dit event.', 'bso-survival') . '</p>';
            echo '<div id="bso-lifecycle-persisted-content" style="display:none;">';
            echo '<p><strong>' . esc_html__('Headline', 'bso-survival') . ':</strong> <span id="bso-lifecycle-persisted-headline">-</span></p>';
            echo '<p><strong>' . esc_html__('Published at', 'bso-survival') . ':</strong> <span id="bso-lifecycle-persisted-published-at">-</span></p>';
            echo '<p><strong>' . esc_html__('Teams in eindstand', 'bso-survival') . ':</strong> <span id="bso-lifecycle-persisted-count">0</span></p>';
            echo '<p><strong>' . esc_html__('Top 3', 'bso-survival') . ':</strong></p>';
            echo '<ol id="bso-lifecycle-persisted-top3" style="margin-left:1.2em;"></ol>';
            echo '</div>';
        } else {
            $headline = (string) ($publication['headline'] ?? '');
            $publishedAt = (string) ($publication['published_at'] ?? '');
            $finalStandings = isset($publication['final_standings']) && is_array($publication['final_standings'])
                ? $publication['final_standings']
                : [];
            $topThree = isset($publication['top_3']) && is_array($publication['top_3'])
                ? $publication['top_3']
                : [];

            echo '<p id="bso-lifecycle-persisted-empty" style="display:none;">' . esc_html__('Nog geen persisted publicatieresultaat gevonden voor dit event.', 'bso-survival') . '</p>';
            echo '<div id="bso-lifecycle-persisted-content">';
            echo '<p><strong>' . esc_html__('Headline', 'bso-survival') . ':</strong> <span id="bso-lifecycle-persisted-headline">' . esc_html($headline !== '' ? $headline : '-') . '</span></p>';
            echo '<p><strong>' . esc_html__('Published at', 'bso-survival') . ':</strong> <span id="bso-lifecycle-persisted-published-at">' . esc_html($publishedAt !== '' ? $publishedAt : '-') . '</span></p>';
            echo '<p><strong>' . esc_html__('Teams in eindstand', 'bso-survival') . ':</strong> <span id="bso-lifecycle-persisted-count">' . (int) count($finalStandings) . '</span></p>';
            echo '<p><strong>' . esc_html__('Top 3', 'bso-survival') . ':</strong></p>';

            if ($topThree === []) {
                echo '<ol id="bso-lifecycle-persisted-top3" style="margin-left:1.2em;"><li>' . esc_html__('Geen top-3 opgeslagen.', 'bso-survival') . '</li></ol>';
            } else {
                echo '<ol id="bso-lifecycle-persisted-top3" style="margin-left:1.2em;">';
                foreach ($topThree as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $rank = (int) ($item['rank'] ?? 0);
                    $teamName = (string) ($item['team_name'] ?? __('Onbekend team', 'bso-survival'));
                    $points = isset($item['points']) ? (float) $item['points'] : 0.0;
                    echo '<li>' . esc_html(sprintf('#%d %s (%.2f pt)', $rank, $teamName, $points)) . '</li>';
                }
                echo '</ol>';
            }
            echo '</div>';
        }

        echo '<details style="margin-top:8px;">';
        echo '<summary>' . esc_html__('Raw persisted payload', 'bso-survival') . '</summary>';
        echo '<pre id="bso-lifecycle-persisted-raw" style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:240px;overflow:auto;">' . esc_html((string) $publicationJson) . '</pre>';
        echo '</details>';

        echo '</div>';

        echo '<p>';
        echo '<button type="button" id="bso-lifecycle-fill-closeout" class="button">' . esc_html__('Voorbeeld closeout laden', 'bso-survival') . '</button> ';
        echo '<button type="button" id="bso-lifecycle-fill-publication" class="button">' . esc_html__('Voorbeeld publicatie laden', 'bso-survival') . '</button> ';
        echo '<button type="button" id="bso-lifecycle-validate-json" class="button">' . esc_html__('JSON valideren', 'bso-survival') . '</button> ';
        echo '<button type="button" id="bso-lifecycle-clear-json" class="button button-link-delete">' . esc_html__('JSON velden leegmaken', 'bso-survival') . '</button>';
        echo '</p>';
        echo '<div id="bso-lifecycle-status" class="notice inline" style="display:none;"><p></p></div>';

        echo '<table class="form-table" role="presentation" style="max-width:860px;">';
        echo '<tbody>';

        echo '<tr>';
        echo '<th scope="row"><label for="bso-lifecycle-changed-by">' . esc_html__('Changed by', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-lifecycle-changed-by" type="text" class="regular-text" value="' . esc_attr($defaultChangedBy) . '" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bso-lifecycle-certificates">' . esc_html__('Certificates JSON', 'bso-survival') . '</label></th>';
        echo '<td><textarea id="bso-lifecycle-certificates" class="large-text code" rows="4">[]</textarea>';
        echo '<p class="description">' . esc_html__('Voor closeout: lijst met certificaten ({team_id, file_path, meta}).', 'bso-survival') . '</p></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bso-lifecycle-headline">' . esc_html__('Publicatie headline', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-lifecycle-headline" type="text" class="regular-text" value="Uitslag gepubliceerd" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bso-lifecycle-published-at">' . esc_html__('Published at (optioneel)', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-lifecycle-published-at" type="text" class="regular-text" value="" placeholder="2026-07-08T12:30:00+00:00" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bso-lifecycle-standings">' . esc_html__('Standings JSON', 'bso-survival') . '</label></th>';
        echo '<td><textarea id="bso-lifecycle-standings" class="large-text code" rows="8">[]</textarea>';
        echo '<p class="description">' . esc_html__('Voor publicatie: lijst met eindstand-items ({rank, team_id, team_name, points}).', 'bso-survival') . '</p></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="bso-lifecycle-recipients">' . esc_html__('Recipients (comma separated)', 'bso-survival') . '</label></th>';
        echo '<td><input id="bso-lifecycle-recipients" type="text" class="large-text" value="" />';
        echo '<p><label><input id="bso-lifecycle-send-notifications" type="checkbox" checked="checked" /> ' . esc_html__('Notificaties versturen bij publicatie', 'bso-survival') . '</label></p>';
        echo '<p class="description">' . esc_html__('Optioneel voor notificaties na publicatie.', 'bso-survival') . '</p></td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        echo '<p>';
        echo '<button type="button" id="bso-lifecycle-closeout" class="button button-secondary">' . esc_html__('Event afsluiten (closeout)', 'bso-survival') . '</button> ';
        echo '<button type="button" id="bso-lifecycle-publish" class="button button-primary">' . esc_html__('Event publiceren', 'bso-survival') . '</button>';
        echo '</p>';
        echo '<h2>' . esc_html__('Publicatie preview', 'bso-survival') . '</h2>';
        echo '<div id="bso-lifecycle-preview" class="notice inline" style="display:block;">';
        echo '<p><strong>' . esc_html__('Standings items', 'bso-survival') . ':</strong> <span id="bso-lifecycle-preview-count">0</span></p>';
        echo '<p><strong>' . esc_html__('Top 3', 'bso-survival') . ':</strong></p>';
        echo '<ol id="bso-lifecycle-preview-top3"><li>' . esc_html__('Nog geen data', 'bso-survival') . '</li></ol>';
        echo '</div>';
        echo '<h2>' . esc_html__('Laatste response', 'bso-survival') . '</h2>';
        echo '<pre id="bso-lifecycle-response" style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:380px;overflow:auto;">{}</pre>';

        echo '</div>';
        echo '</div>';
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

        return 'beheerder';
    }
}