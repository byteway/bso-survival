<?php

namespace BSO\Survival\Service;

use BSO\Survival\Contracts\DashboardWidgetInterface;
use BSO\Survival\Widgets\ContactFinderWidget;
use BSO\Survival\Widgets\FallbackScoreWidget;
use BSO\Survival\Widgets\MessageWidget;
use BSO\Survival\Widgets\ReportingStatusWidget;
use BSO\Survival\Widgets\TeamRankingWidget;
use BSO\Survival\Widgets\TimeslotProgressWidget;

class DashboardWidgetRegistry {
    /** @var array<string, array<string, DashboardWidgetInterface>> */
    private static $widgets = [];

    public static function register(string $section, DashboardWidgetInterface $widget): void {
        if (!isset(self::$widgets[$section])) {
            self::$widgets[$section] = [];
        }

        self::$widgets[$section][$widget->getId()] = $widget;
    }

    /**
     * @return array<string, DashboardWidgetInterface>
     */
    public static function getSection(string $section): array {
        return self::$widgets[$section] ?? [];
    }

    public static function get(string $section, string $id): ?DashboardWidgetInterface {
        return self::$widgets[$section][$id] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function getSectionIds(): array {
        return array_keys(self::$widgets);
    }

    /**
     * @return array<int, string>
     */
    public static function getSectionWidgetIds(string $section): array {
        $ids = [];
        foreach (self::getWidgetsForSection($section) as $widget) {
            $ids[] = $widget->getId();
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $overview
     * @param array<string, mixed> $filters
     */
    public static function renderSection(string $section, array $overview, array $filters = []): string {
        $widgets = self::getWidgetsForSection($section, $filters);

        $safeSection = function_exists('esc_attr')
            ? esc_attr($section)
            : htmlspecialchars($section, ENT_QUOTES, 'UTF-8');

        $html = '<div class="dashboard-section dashboard-section-' . $safeSection . '">';
        foreach ($widgets as $widget) {
            if (!self::canViewWidget($widget)) {
                continue;
            }

            $context = [
                'overview' => $overview,
                'data' => $widget->getData($overview, $filters),
                'widget_id' => $widget->getId(),
            ];
            $html .= $widget->render($context);
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @return array<int, string>
     */
    public static function getSectionScriptDependencies(string $section, array $filters = []): array {
        return self::getSectionDependencies($section, 'script', $filters);
    }

    /**
     * @return array<int, string>
     */
    public static function getSectionStyleDependencies(string $section, array $filters = []): array {
        return self::getSectionDependencies($section, 'style', $filters);
    }

    public static function initDefaults(): void {
        self::register('main', new TimeslotProgressWidget());
        self::register('main', new TeamRankingWidget());
        self::register('main', new ReportingStatusWidget());
        self::register('operations', new MessageWidget());
        self::register('operations', new ContactFinderWidget());
        self::register('operations', new FallbackScoreWidget());

        if (function_exists('do_action')) {
            do_action('bso_survival_dashboard_widgets_init', self::class);
        }
    }

    public static function reset(): void {
        self::$widgets = [];
    }

    private static function canViewWidget(DashboardWidgetInterface $widget): bool {
        $caps = $widget->getCapabilities();
        if (empty($caps)) {
            return true;
        }

        if (!function_exists('current_user_can')) {
            return false;
        }

        foreach ($caps as $cap) {
            if (current_user_can($cap)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function getSectionDependencies(string $section, string $type, array $filters = []): array {
        $dependencies = [];

        foreach (self::getWidgetsForSection($section, $filters) as $widget) {
            if (!self::canViewWidget($widget)) {
                continue;
            }

            $handles = $type === 'script'
                ? $widget->getScriptDependencies()
                : $widget->getStyleDependencies();

            foreach ($handles as $handle) {
                if (is_string($handle) && $handle !== '') {
                    $dependencies[$handle] = $handle;
                }
            }
        }

        return array_values($dependencies);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, DashboardWidgetInterface>
     */
    private static function getWidgetsForSection(string $section, array $filters = []): array {
        $widgets = array_values(self::getSection($section));
        usort($widgets, static function (DashboardWidgetInterface $a, DashboardWidgetInterface $b): int {
            return $a->getPriority() <=> $b->getPriority();
        });

        if (!isset($filters['widget_ids']) || !is_array($filters['widget_ids'])) {
            return $widgets;
        }

        $byId = [];
        foreach ($widgets as $widget) {
            $byId[$widget->getId()] = $widget;
        }

        $selected = [];
        foreach ($filters['widget_ids'] as $widgetId) {
            if (!is_string($widgetId)) {
                continue;
            }

            if (isset($byId[$widgetId])) {
                $selected[$widgetId] = $byId[$widgetId];
            }
        }

        return array_values($selected);
    }
}
