<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class ReportingStatusWidget implements DashboardWidgetInterface {
    public function getId(): string { return 'reporting_status'; }
    public function getTitle(): string { return 'Onderdeel-rapportagestatus'; }
    public function getPriority(): int { return 30; }
    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array {
        $parts = (int) ($overview['counts']['parts'] ?? 0);
        $hasParts = (bool) ($overview['status']['has_parts'] ?? false);

        return [
            'status_text' => $hasParts ? sprintf('%d onderdelen actief', $parts) : 'Geen onderdelen actief',
        ];
    }

    public function render(array $context): string {
        return '<article class="bso-widget bso-widget-reporting"><h3>' . esc_html($this->getTitle()) . '</h3><p>' .
            esc_html((string) ($context['data']['status_text'] ?? 'Onbekend')) . '</p></article>';
    }

    public function getScriptDependencies(): array { return []; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }
}
