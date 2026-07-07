<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class TimeslotProgressWidget implements DashboardWidgetInterface {
    public function getId(): string {
        return 'timeslot_progress';
    }

    public function getTitle(): string {
        return 'Tijdslot voortgang';
    }

    public function getPriority(): int {
        return 10;
    }

    public function getCapabilities(): array {
        return ['read'];
    }

    public function getData(array $overview, array $filters = []): array {
        $parts = (int) ($overview['counts']['parts'] ?? 0);
        $teams = (int) ($overview['counts']['teams'] ?? 0);
        $progress = $parts > 0 ? (int) min(100, round(($teams / max(1, $parts * 2)) * 100)) : 0;

        return ['progress' => $progress];
    }

    public function render(array $context): string {
        $data = $context['data'];
        $progress = (int) ($data['progress'] ?? 0);

        return '<article class="bso-widget bso-widget-timeslot"><h3>' . esc_html($this->getTitle()) . '</h3>' .
            '<p>' . esc_html(sprintf('Voortgangsindicatie: %d%%', $progress)) . '</p></article>';
    }

    public function getScriptDependencies(): array {
        return ['bso-survival-dashboard-widgets'];
    }

    public function getStyleDependencies(): array {
        return ['bso-survival-dashboard-widgets'];
    }
}
