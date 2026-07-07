<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class TeamRankingWidget implements DashboardWidgetInterface {
    public function getId(): string { return 'team_ranking'; }
    public function getTitle(): string { return 'Teampositieoverzicht'; }
    public function getPriority(): int { return 20; }
    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array {
        $teams = $overview['teams'] ?? [];
        $names = [];
        foreach (array_slice($teams, 0, 3) as $team) {
            $names[] = (string) ($team->name ?? 'Onbekend team');
        }

        return ['top_teams' => $names];
    }

    public function render(array $context): string {
        $teams = $context['data']['top_teams'] ?? [];
        $items = '';
        foreach ($teams as $name) {
            $items .= '<li>' . esc_html((string) $name) . '</li>';
        }

        if ($items === '') {
            $items = '<li>' . esc_html__('Nog geen teams beschikbaar', 'bso-survival') . '</li>';
        }

        return '<article class="bso-widget bso-widget-ranking"><h3>' . esc_html($this->getTitle()) . '</h3><ul>' . $items . '</ul></article>';
    }

    public function getScriptDependencies(): array { return []; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }
}
