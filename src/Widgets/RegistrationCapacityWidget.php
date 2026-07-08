<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class RegistrationCapacityWidget implements DashboardWidgetInterface {
    public function getId(): string { return 'registration_capacity'; }
    public function getTitle(): string { return 'Inschrijfcapaciteit'; }
    public function getPriority(): int { return 15; }
    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array {
        $registered = (int) ($overview['counts']['registered_teams'] ?? $overview['counts']['teams'] ?? 0);
        $maxTeams = (int) ($overview['counts']['max_teams'] ?? 0);
        $isFull = (bool) ($overview['status']['is_registration_full'] ?? false);

        return [
            'registered' => $registered,
            'max_teams' => $maxTeams,
            'is_full' => $isFull,
        ];
    }

    public function render(array $context): string {
        $data = is_array($context['data'] ?? null) ? $context['data'] : [];
        $registered = (int) ($data['registered'] ?? 0);
        $maxTeams = (int) ($data['max_teams'] ?? 0);
        $isFull = (bool) ($data['is_full'] ?? false);

        $value = $maxTeams > 0
            ? sprintf('%d / %d', $registered, $maxTeams)
            : sprintf('%d / ?', $registered);

        $badge = $isFull
            ? '<span class="bso-widget-badge bso-widget-badge--full">VOL</span>'
            : '';

        return '<article class="bso-widget bso-widget-registration-capacity"><h3>' . esc_html($this->getTitle()) . '</h3><p>' . esc_html($value) . '</p>' . $badge . '</article>';
    }

    public function getScriptDependencies(): array { return []; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }
}
