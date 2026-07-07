<?php

namespace BSO\Survival\Database\Repository;

class DashboardWidgetLayoutRepository implements DashboardWidgetLayoutRepositoryInterface {
    private const OPTION_KEY = 'bso_survival_dashboard_widget_layouts';

    /** @var array<string, mixed> */
    private static $memoryStore = [];

    public function getByEventId(int $eventId): array {
        $allLayouts = $this->getAllLayouts();
        $eventKey = (string) $eventId;

        $layout = $allLayouts[$eventKey] ?? [];
        return is_array($layout) ? $layout : [];
    }

    public function saveByEventId(int $eventId, array $layout): void {
        $allLayouts = $this->getAllLayouts();
        $allLayouts[(string) $eventId] = $layout;

        if (function_exists('update_option')) {
            update_option(self::OPTION_KEY, $allLayouts, false);
            return;
        }

        self::$memoryStore[self::OPTION_KEY] = $allLayouts;
    }

    /**
     * @return array<string, mixed>
     */
    private function getAllLayouts(): array {
        if (function_exists('get_option')) {
            $stored = get_option(self::OPTION_KEY, []);
            return is_array($stored) ? $stored : [];
        }

        $stored = self::$memoryStore[self::OPTION_KEY] ?? [];
        return is_array($stored) ? $stored : [];
    }
}
