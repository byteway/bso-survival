<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class ContactFinderWidget implements DashboardWidgetInterface {
    public function getId(): string { return 'contact_finder'; }
    public function getTitle(): string { return 'Contactzoeker'; }
    public function getPriority(): int { return 50; }
    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array {
        return [
            'hint' => 'Zoek contactgegevens via teambeheer.',
        ];
    }

    public function render(array $context): string {
        return '<article class="bso-widget bso-widget-contact"><h3>' . esc_html($this->getTitle()) . '</h3><p>' .
            esc_html((string) ($context['data']['hint'] ?? '')) . '</p></article>';
    }

    public function getScriptDependencies(): array { return []; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }
}
