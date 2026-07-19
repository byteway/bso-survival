<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class TeamRankingWidget implements DashboardWidgetInterface {
    public function getId(): string { return 'team_ranking'; }
    public function getTitle(): string { return 'Podiumplekken'; }
    public function getPriority(): int { return 20; }
    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array {
        $publication = is_array($overview['publication'] ?? null) ? $overview['publication'] : [];
        $finalStandings = isset($publication['final_standings']) && is_array($publication['final_standings'])
            ? $publication['final_standings']
            : [];

        if ($finalStandings !== []) {
            $ranked = [];
            foreach (array_slice($finalStandings, 0, 3) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $ranked[] = [
                    'rank' => (int) ($item['rank'] ?? 0),
                    'team_name' => (string) ($item['team_name'] ?? 'Onbekend team'),
                    'points' => (float) ($item['points'] ?? 0),
                ];
            }

            return [
                'top_teams' => $ranked,
                'source' => 'published_final_standings',
            ];
        }

        $teams = $overview['teams'] ?? [];
        $names = [];
        foreach (array_slice($teams, 0, 3) as $team) {
            $names[] = [
                'rank' => count($names) + 1,
                'team_name' => (string) ($team->name ?? 'Onbekend team'),
                'points' => null,
            ];
        }

        return [
            'top_teams' => $names,
            'source' => 'team_list_fallback',
        ];
    }

    public function render(array $context): string {
        $teams = $context['data']['top_teams'] ?? [];
        $items = '';
        foreach ($teams as $team) {
            if (!is_array($team)) {
                continue;
            }

            $rank = (int) ($team['rank'] ?? 0);
            $name = (string) ($team['team_name'] ?? 'Onbekend team');
            $points = $team['points'] ?? null;

            $label = $rank > 0
                ? sprintf('#%d %s', $rank, $name)
                : $name;

            if (is_numeric($points)) {
                $label .= sprintf(' (%.2f pt)', (float) $points);
            }

            $items .= '<li>' . esc_html($label) . '</li>';
        }

        if ($items === '') {
            $items = '<li>' . esc_html__('Nog geen teams beschikbaar', 'bso-survival') . '</li>';
        }

        return '<article class="bso-widget bso-widget-ranking"><h3>' . esc_html($this->getTitle()) . '</h3><ul>' . $items . '</ul></article>';
    }

    public function getScriptDependencies(): array { return []; }
    public function getStyleDependencies(): array { return ['bso-survival-dashboard-widgets']; }
}
