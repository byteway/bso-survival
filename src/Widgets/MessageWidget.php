<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class MessageWidget implements DashboardWidgetInterface {
    public function getId(): string { return 'message_widget'; }
    public function getTitle(): string { return 'Meldingen'; }
    public function getPriority(): int { return 40; }
    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array {
        $eventName = (string) ($overview['event']->name ?? 'event');

        return [
            'message' => sprintf('Dashboard actief voor %s.', $eventName),
        ];
    }

    public function render(array $context): string {
        return '<article class="bso-widget bso-widget-message"><h3>' . esc_html($this->getTitle()) . '</h3><p>' .
            esc_html((string) ($context['data']['message'] ?? '')) . '</p></article>';
    }

    public function getScriptDependencies(): array { return []; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }
}
