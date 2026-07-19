<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;
use BSO\Survival\Service\RegistrationWindowService;

class RegistrationCapacityWidget implements DashboardWidgetInterface {
    /** @var RegistrationWindowService */
    private $registrationWindows;

    public function __construct(RegistrationWindowService $registrationWindows = null) {
        if ($registrationWindows !== null) {
            $this->registrationWindows = $registrationWindows;
            return;
        }

        $wpdb = null;
        if (isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb'])) {
            $wpdb = $GLOBALS['wpdb'];
        }

        if ($wpdb !== null) {
            $this->registrationWindows = new RegistrationWindowService(new \BSO\Survival\Database\Repository\RegistrationWindowRepository($wpdb));
            return;
        }

        $this->registrationWindows = new class extends RegistrationWindowService {
            public function __construct() {
            }

            public function isOpenForEvent(int $eventId, string $momentUtc = ''): bool {
                return true;
            }
        };
    }

    public function getId(): string { return 'registration_capacity'; }
    public function getTitle(): string { return 'Inschrijfcapaciteit'; }
    public function getPriority(): int { return 15; }
    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array {
        $registered = (int) ($overview['counts']['registered_teams'] ?? $overview['counts']['teams'] ?? 0);
        $maxTeams = (int) ($overview['counts']['max_teams'] ?? 0);
        $eventId = (int) ($filters['event_id'] ?? ($overview['event']->id ?? 0));
        $isOpen = $this->registrationWindows->isOpenForEvent($eventId);
        $remaining = $maxTeams > 0 ? max(0, $maxTeams - $registered) : null;
        $utilization = $maxTeams > 0
            ? min(100, (int) round(($registered / max(1, $maxTeams)) * 100))
            : 0;
        $limitedThreshold = $this->getLimitedThreshold($maxTeams);
        $isFull = $maxTeams > 0 && $remaining === 0;
        $status = $isFull
            ? 'full'
            : (!$isOpen
                ? 'closed'
                : ($remaining !== null && $remaining > 0 && $remaining <= $limitedThreshold ? 'limited' : 'open'));

        $statusLabels = [
            'open' => 'Open voor inschrijvingen',
            'limited' => 'Beperkt aantal plaatsen beschikbaar',
            'full' => 'Volgeboekt',
            'closed' => 'Inschrijvingen gesloten',
        ];

        return [
            'registered' => $registered,
            'max_teams' => $maxTeams,
            'remaining' => $remaining,
            'utilization' => $utilization,
            'status' => $status,
            'status_label' => $statusLabels[$status] ?? $statusLabels['closed'],
            'status_class' => 'is-' . $status,
            'status_badge' => $status === 'full' ? 'VOL' : ($status === 'closed' ? 'Gesloten' : ($status === 'limited' ? 'Beperkt' : 'Open')),
            'is_full' => $isFull,
        ];
    }

    public function render(array $context): string {
        $data = is_array($context['data'] ?? null) ? $context['data'] : [];
        $registered = (int) ($data['registered'] ?? 0);
        $maxTeams = (int) ($data['max_teams'] ?? 0);
        $remaining = array_key_exists('remaining', $data) ? $data['remaining'] : null;
        $utilization = (int) ($data['utilization'] ?? 0);
        $status = (string) ($data['status'] ?? 'closed');
        $statusLabel = (string) ($data['status_label'] ?? 'Inschrijvingen gesloten');
        $statusClass = (string) ($data['status_class'] ?? 'is-closed');
        $statusBadge = (string) ($data['status_badge'] ?? 'Gesloten');

        $value = $maxTeams > 0
            ? sprintf('%d / %d', $registered, $maxTeams)
            : sprintf('%d / ?', $registered);

        $remainingText = $remaining !== null
            ? sprintf(
                (int) $remaining === 1 ? __('%d beschikbare plaats', 'bso-survival') : __('%d beschikbare plaatsen', 'bso-survival'),
                (int) $remaining
            )
            : __('Capaciteit onbekend', 'bso-survival');

        $progressStyle = $maxTeams > 0
            ? sprintf(' style="width: %d%%;"', max(0, min(100, $utilization)))
            : '';

        $statusBadgeClass = 'bso-widget-badge--available';
        if ($status === 'full') {
            $statusBadgeClass = 'bso-widget-badge--full';
        } elseif ($status === 'closed') {
            $statusBadgeClass = 'bso-widget-badge--closed';
        } elseif ($status === 'limited') {
            $statusBadgeClass = 'bso-widget-badge--limited';
        }

        $html = '<article class="bso-widget bso-widget-registration-capacity bso-widget-registration-capacity--' . esc_attr($statusClass) . '">';
        $html .= '<h3>' . esc_html($this->getTitle()) . '</h3>';
        $html .= '<p class="bso-widget-registration-capacity__value">' . esc_html($value) . '</p>';
        $html .= '<div class="bso-widget-registration-capacity__meta">';
        $html .= '<span class="bso-widget-registration-capacity__remaining">' . esc_html($remainingText) . '</span>';
        $html .= '<span class="bso-widget-badge ' . esc_attr($statusBadgeClass) . '"><span class="bso-widget-badge__dot" aria-hidden="true"></span>' . esc_html($statusBadge) . '</span>';
        $html .= '</div>';
        $html .= '<div class="bso-widget-registration-capacity__progress" role="img" aria-label="' . esc_attr(sprintf(__('Bezetting %d procent', 'bso-survival'), $utilization)) . '">';
        $html .= '<span class="bso-widget-registration-capacity__progress-fill"' . $progressStyle . '></span>';
        $html .= '</div>';
        $html .= '<p class="bso-widget-registration-capacity__status">' . esc_html($statusLabel) . '</p>';
        $html .= '</article>';

        return $html;
    }

    public function getScriptDependencies(): array { return []; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }

    private function getLimitedThreshold(int $maxTeams): int {
        if ($maxTeams <= 0) {
            return 0;
        }

        return max(1, min(5, (int) ceil($maxTeams * 0.2)));
    }
}
