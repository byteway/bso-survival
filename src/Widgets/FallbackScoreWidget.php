<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class FallbackScoreWidget implements DashboardWidgetInterface {
    public function getId(): string { return 'fallback_score'; }
    public function getTitle(): string { return 'Fallback-scoreinvoer'; }
    public function getPriority(): int { return 60; }
    public function getCapabilities(): array { return ['manage_options']; }

    public function getData(array $overview, array $filters = []): array {
        return [
            'text' => 'Gebruik alleen bij storing van reguliere scoreflow.',
        ];
    }

    public function render(array $context): string {
        return '<article class="bso-widget bso-widget-fallback"><h3>' . esc_html($this->getTitle()) . '</h3><p>' .
            esc_html((string) ($context['data']['text'] ?? '')) . '</p></article>';
    }

    public function getScriptDependencies(): array { return []; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }
}
