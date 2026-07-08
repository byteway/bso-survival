<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\RegistrationWindowService;
use BSO\Survival\Service\TeamService;

class RegistrationAdminPage {
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
            'manage_options',
            'bso-survival-registrations',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }

        $events = $this->events->listEvents();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Inschrijvingsbeheer', 'bso-survival') . '</h1>';
        echo '<p>' . esc_html__('Overzicht van teaminschrijvingen per event.', 'bso-survival') . '</p>';

        if ($events === []) {
            echo '<p>' . esc_html__('Geen events gevonden.', 'bso-survival') . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Event', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Status', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Inschrijving', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Bezetting', 'bso-survival') . '</th>';
        echo '<th>' . esc_html__('Venster', 'bso-survival') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($events as $event) {
            $eventId = (int) ($event->id ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            $registered = $this->teams->countTeamsForEvent($eventId);
            $maxTeams = $this->extractMaxTeams((string) ($event->meta_data ?? ''));
            $status = (string) ($event->status ?? 'onbekend');
            $windowOpen = $this->windows->isOpenForEvent($eventId);
            $occupancy = $maxTeams > 0
                ? min(100, (int) round(($registered / $maxTeams) * 100))
                : 0;

            echo '<tr>';
            echo '<td>' . esc_html(sprintf('#%d %s', $eventId, (string) ($event->name ?? ''))) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . esc_html($maxTeams > 0 ? sprintf('%d / %d', $registered, $maxTeams) : sprintf('%d / ?', $registered)) . '</td>';
            echo '<td>' . esc_html($maxTeams > 0 ? ($occupancy . '%') : '-') . '</td>';
            echo '<td>' . esc_html($windowOpen ? __('open', 'bso-survival') : __('gesloten', 'bso-survival')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
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
