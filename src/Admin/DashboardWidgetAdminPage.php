<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\DashboardWidgetLayoutService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use BSO\Survival\Service\EventService;

class DashboardWidgetAdminPage {
    private const SAVE_NONCE_ACTION = 'bso_survival_save_dashboard_widget_layout';
    private const SAVE_NONCE_FIELD = 'bso_survival_save_dashboard_widget_layout_nonce';

    /** @var EventService */
    private $events;

    /** @var DashboardWidgetLayoutService */
    private $layoutService;

    public function __construct(EventService $events, DashboardWidgetLayoutService $layoutService) {
        $this->events = $events;
        $this->layoutService = $layoutService;
    }

    public function registerMenu(): void {
        if (function_exists('add_submenu_page')) {
            add_submenu_page(
                'bso-survival-rules',
                __('Dashboard Widgets', 'bso-survival'),
                __('Dashboard Widgets', 'bso-survival'),
                'manage_options',
                'bso-survival-dashboard-widgets',
                [$this, 'renderPage']
            );
            return;
        }

        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            __('Dashboard Widgets', 'bso-survival'),
            __('Dashboard Widgets', 'bso-survival'),
            'manage_options',
            'bso-survival-dashboard-widgets',
            [$this, 'renderPage'],
            'dashicons-screenoptions',
            59
        );
    }

    public function handleSave(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }

        if (!isset($_POST[self::SAVE_NONCE_FIELD]) || !wp_verify_nonce((string) $_POST[self::SAVE_NONCE_FIELD], self::SAVE_NONCE_ACTION)) {
            wp_die(__('Ongeldige aanvraag (nonce).', 'bso-survival'));
        }

        $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($eventId <= 0) {
            wp_die(__('Ongeldig event.', 'bso-survival'));
        }

        $layout = $this->extractLayoutFromRequest();
        $this->layoutService->saveLayoutForEvent($eventId, $layout);

        $redirect = add_query_arg(
            [
                'page' => 'bso-survival-dashboard-widgets',
                'event_id' => $eventId,
                'saved' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function renderPage(): void {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }

        if (DashboardWidgetRegistry::getSectionIds() === []) {
            DashboardWidgetRegistry::initDefaults();
        }

        $events = $this->events->listEvents();
        $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
        if ($eventId <= 0 && !empty($events)) {
            $eventId = (int) $events[0]->id;
        }

        $layout = $eventId > 0 ? $this->layoutService->getLayoutForEvent($eventId) : [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('BSO Survival Dashboard Widgets', 'bso-survival') . '</h1>';

        if (isset($_GET['saved']) && (int) $_GET['saved'] === 1) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Dashboardlayout opgeslagen.', 'bso-survival') . '</p></div>';
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-survival-dashboard-widgets" />';
        echo '<label for="bso-dashboard-event-id"><strong>' . esc_html__('Event', 'bso-survival') . ':</strong></label> ';
        echo '<select id="bso-dashboard-event-id" name="event_id">';
        foreach ($events as $event) {
            $selected = selected($eventId, (int) $event->id, false);
            echo '<option value="' . (int) $event->id . '" ' . $selected . '>' . esc_html($event->name) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button">' . esc_html__('Laden', 'bso-survival') . '</button>';
        echo '</form>';

        echo '<hr />';

        if ($eventId <= 0) {
            echo '<p>' . esc_html__('Geen event beschikbaar.', 'bso-survival') . '</p>';
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_save_dashboard_widgets" />';
        echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

        foreach (DashboardWidgetRegistry::getSectionIds() as $section) {
            $widgetIds = DashboardWidgetRegistry::getSectionWidgetIds($section);
            $enabledIds = $layout[$section] ?? $widgetIds;
            $orderLookup = array_flip($enabledIds);

            echo '<h2>' . esc_html(ucfirst($section)) . '</h2>';
            echo '<table class="widefat striped" style="max-width:900px;">';
            echo '<thead><tr><th>' . esc_html__('Actief', 'bso-survival') . '</th><th>' . esc_html__('Widget', 'bso-survival') . '</th><th>' . esc_html__('Volgorde', 'bso-survival') . '</th></tr></thead><tbody>';

            foreach ($widgetIds as $index => $widgetId) {
                $widget = DashboardWidgetRegistry::get($section, $widgetId);
                if ($widget === null) {
                    continue;
                }

                $isEnabled = in_array($widgetId, $enabledIds, true);
                $position = isset($orderLookup[$widgetId]) ? (int) $orderLookup[$widgetId] + 1 : $index + 1;

                echo '<tr>';
                echo '<td><label><input type="checkbox" name="layout[' . esc_attr($section) . '][]" value="' . esc_attr($widgetId) . '" ' . checked($isEnabled, true, false) . ' /> ' . esc_html__('Inschakelen', 'bso-survival') . '</label></td>';
                echo '<td>' . esc_html($widget->getTitle()) . '<br /><small>' . esc_html($widgetId) . '</small></td>';
                echo '<td><input type="number" min="1" step="1" name="order[' . esc_attr($section) . '][' . esc_attr($widgetId) . ']" value="' . esc_attr((string) $position) . '" /></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '<p><button class="button button-primary">' . esc_html__('Layout opslaan', 'bso-survival') . '</button></p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function extractLayoutFromRequest(): array {
        $rawLayout = isset($_POST['layout']) && is_array($_POST['layout']) ? $_POST['layout'] : [];
        $rawOrder = isset($_POST['order']) && is_array($_POST['order']) ? $_POST['order'] : [];

        $layout = [];
        foreach (DashboardWidgetRegistry::getSectionIds() as $section) {
            $sectionEnabled = isset($rawLayout[$section]) && is_array($rawLayout[$section]) ? $rawLayout[$section] : [];
            $sectionOrder = isset($rawOrder[$section]) && is_array($rawOrder[$section]) ? $rawOrder[$section] : [];

            $ranked = [];
            foreach ($sectionEnabled as $index => $widgetId) {
                $cleanId = sanitize_key((string) $widgetId);
                if ($cleanId === '') {
                    continue;
                }

                $rank = isset($sectionOrder[$cleanId]) ? (int) $sectionOrder[$cleanId] : (int) $index + 1;
                $ranked[] = [
                    'id' => $cleanId,
                    'rank' => $rank,
                    'index' => (int) $index,
                ];
            }

            usort($ranked, static function (array $a, array $b): int {
                if ($a['rank'] === $b['rank']) {
                    return $a['index'] <=> $b['index'];
                }

                return $a['rank'] <=> $b['rank'];
            });

            $layout[$section] = array_values(array_map(static function (array $item): string {
                return $item['id'];
            }, $ranked));
        }

        return $layout;
    }
}
