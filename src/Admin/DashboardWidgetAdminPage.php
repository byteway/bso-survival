<?php

namespace BSO\Survival\Admin;

use BSO\Survival\Service\DashboardWidgetLayoutService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use BSO\Survival\Service\EventService;
use BSO\Survival\Support\Capabilities;

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
                Capabilities::MANAGE_SETTINGS,
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
            Capabilities::MANAGE_SETTINGS,
            'bso-survival-dashboard-widgets',
            [$this, 'renderPage'],
            'dashicons-screenoptions',
            59
        );
    }

    public function handleSave(): void {
        if (!Capabilities::canManageSettings()) {
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
        if (!Capabilities::canManageSettings()) {
            wp_die(__('Onvoldoende rechten.', 'bso-survival'));
        }

        wp_enqueue_style('bso-survival-admin-dashboard-widgets');
        wp_enqueue_script('bso-survival-admin-dashboard-widgets');

        if (DashboardWidgetRegistry::getSectionIds() === []) {
            DashboardWidgetRegistry::initDefaults();
        }

        $events = $this->events->listEvents();
        $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
        if ($eventId <= 0 && !empty($events)) {
            $eventId = (int) $events[0]->id;
        }

        $layout = $eventId > 0 ? $this->layoutService->getLayoutForEvent($eventId) : [];
        $navigation = isset($layout['navigation']) && is_array($layout['navigation']) ? $layout['navigation'] : [];
        $partsHelpPageId = isset($navigation['parts_help_page_id']) ? (int) $navigation['parts_help_page_id'] : 0;
        $teamScorePageId = isset($navigation['team_score_page_id']) ? (int) $navigation['team_score_page_id'] : 0;
        $pages = $this->listPublishedPages();

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

        $restBaseUrl = function_exists('rest_url')
            ? (string) rest_url('bso-survival/v1/dashboard-layout')
            : '';
        $restNonce = function_exists('wp_create_nonce')
            ? (string) wp_create_nonce('wp_rest')
            : '';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="bso-dashboard-widget-layout-form" data-rest-base="' . esc_attr($restBaseUrl) . '" data-rest-nonce="' . esc_attr($restNonce) . '">';
        echo '<input type="hidden" name="action" value="bso_survival_save_dashboard_widgets" />';
        echo '<input type="hidden" name="event_id" value="' . (int) $eventId . '" />';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);
        echo '<div class="notice inline bso-widget-save-status" style="display:none;"><p></p></div>';
        echo '<div class="bso-widget-admin-navigation" style="margin:16px 0 24px;max-width:900px;">';
        echo '<h2>' . esc_html__('Dashboard navigatie', 'bso-survival') . '</h2>';
        echo '<p class="description">' . esc_html__('Kies op welke pagina de dashboardlinks voor onderdelen en teams moeten openen.', 'bso-survival') . '</p>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="bso-dashboard-parts-help-page-id">' . esc_html__('Onderdelenlijst pagina', 'bso-survival') . '</label></th>';
        echo '<td><select id="bso-dashboard-parts-help-page-id" name="navigation[parts_help_page_id]">';
        echo '<option value="0">' . esc_html__('Huidige dashboardpagina gebruiken', 'bso-survival') . '</option>';
        foreach ($pages as $page) {
            $pageId = (int) ($page->ID ?? 0);
            if ($pageId <= 0) {
                continue;
            }

            $selected = selected($partsHelpPageId, $pageId, false);
            $title = (string) ($page->post_title ?? '');
            echo '<option value="' . $pageId . '" ' . $selected . '>' . esc_html(sprintf('#%d - %s', $pageId, $title !== '' ? $title : __('(geen titel)', 'bso-survival'))) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="bso-dashboard-team-score-page-id">' . esc_html__('Teamscore pagina', 'bso-survival') . '</label></th>';
        echo '<td><select id="bso-dashboard-team-score-page-id" name="navigation[team_score_page_id]">';
        echo '<option value="0">' . esc_html__('Huidige dashboardpagina gebruiken', 'bso-survival') . '</option>';
        foreach ($pages as $page) {
            $pageId = (int) ($page->ID ?? 0);
            if ($pageId <= 0) {
                continue;
            }

            $selected = selected($teamScorePageId, $pageId, false);
            $title = (string) ($page->post_title ?? '');
            echo '<option value="' . $pageId . '" ' . $selected . '>' . esc_html(sprintf('#%d - %s', $pageId, $title !== '' ? $title : __('(geen titel)', 'bso-survival'))) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';
        echo '</tbody></table>';
        echo '</div>';

        foreach (DashboardWidgetRegistry::getSectionIds() as $section) {
            $widgetIds = DashboardWidgetRegistry::getSectionWidgetIds($section);
            $enabledIds = $layout[$section] ?? $widgetIds;
            $orderLookup = array_flip($enabledIds);
            $widthsByWidget = isset($layout['widths'][$section]) && is_array($layout['widths'][$section]) ? $layout['widths'][$section] : [];

            echo '<div class="bso-widget-admin-section" data-section="' . esc_attr($section) . '">';
            echo '<h2>' . esc_html(ucfirst($section)) . '</h2>';
            echo '<p class="description">' . esc_html__('Sleep rijen voor volgorde, vink widgets aan/uit en bekijk live preview.', 'bso-survival') . '</p>';
            echo '<div class="notice notice-warning inline bso-widget-section-warning" style="display:none;"><p>' . esc_html__('Deze sectie heeft geen actieve widgets. Frontend toont deze sectie leeg.', 'bso-survival') . '</p></div>';

            echo '<table class="widefat striped bso-widget-admin-table" style="max-width:900px;">';
            echo '<thead><tr><th>' . esc_html__('Actief', 'bso-survival') . '</th><th>' . esc_html__('Widget', 'bso-survival') . '</th><th>' . esc_html__('Volgorde', 'bso-survival') . '</th><th>' . esc_html__('Breedte', 'bso-survival') . '</th></tr></thead><tbody>';

            foreach ($widgetIds as $index => $widgetId) {
                $widget = DashboardWidgetRegistry::get($section, $widgetId);
                if ($widget === null) {
                    continue;
                }

                $isEnabled = in_array($widgetId, $enabledIds, true);
                $position = isset($orderLookup[$widgetId]) ? (int) $orderLookup[$widgetId] + 1 : $index + 1;
                $currentWidth = isset($widthsByWidget[$widgetId]) && is_string($widthsByWidget[$widgetId])
                    ? (string) $widthsByWidget[$widgetId]
                    : DashboardWidgetLayoutService::getDefaultWidthForWidget($widgetId);

                echo '<tr class="bso-widget-row" draggable="true" data-widget-id="' . esc_attr($widgetId) . '">';
                echo '<td><label><input type="checkbox" name="layout[' . esc_attr($section) . '][]" value="' . esc_attr($widgetId) . '" ' . checked($isEnabled, true, false) . ' /> ' . esc_html__('Inschakelen', 'bso-survival') . '</label></td>';
                echo '<td><button type="button" class="button-link bso-widget-drag-handle" aria-label="' . esc_attr__('Sleep om te verplaatsen', 'bso-survival') . '">&#x2630;</button> ' . esc_html($widget->getTitle()) . '<br /><small>' . esc_html($widgetId) . '</small></td>';
                echo '<td><input class="bso-widget-order-input" type="number" min="1" step="1" name="order[' . esc_attr($section) . '][' . esc_attr($widgetId) . ']" value="' . esc_attr((string) $position) . '" /></td>';
                echo '<td><select class="bso-widget-width-select" name="width[' . esc_attr($section) . '][' . esc_attr($widgetId) . ']">';
                foreach (DashboardWidgetLayoutService::getWidthOptions() as $option) {
                    $selected = selected($currentWidth, (string) $option['value'], false);
                    echo '<option value="' . esc_attr((string) $option['value']) . '" ' . $selected . '>' . esc_html((string) $option['label']) . '</option>';
                }
                echo '</select></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<div class="bso-widget-preview-wrap">';
            echo '<strong>' . esc_html__('Live preview', 'bso-survival') . '</strong>';
            echo '<ul class="bso-widget-preview-list" data-preview-section="' . esc_attr($section) . '"></ul>';
            echo '</div>';
            echo '</div>';
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
        $rawWidth = isset($_POST['width']) && is_array($_POST['width']) ? $_POST['width'] : [];

        $layout = [];
        $widths = [];
        foreach (DashboardWidgetRegistry::getSectionIds() as $section) {
            $sectionEnabled = isset($rawLayout[$section]) && is_array($rawLayout[$section]) ? $rawLayout[$section] : [];
            $sectionOrder = isset($rawOrder[$section]) && is_array($rawOrder[$section]) ? $rawOrder[$section] : [];
            $sectionWidth = isset($rawWidth[$section]) && is_array($rawWidth[$section]) ? $rawWidth[$section] : [];

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

            $widths[$section] = [];
            foreach (DashboardWidgetRegistry::getSectionWidgetIds($section) as $widgetId) {
                $rawWidgetWidth = isset($sectionWidth[$widgetId]) ? (string) $sectionWidth[$widgetId] : '';
                $widths[$section][$widgetId] = in_array($rawWidgetWidth, array_column(DashboardWidgetLayoutService::getWidthOptions(), 'value'), true)
                    ? $rawWidgetWidth
                    : DashboardWidgetLayoutService::getDefaultWidthForWidget($widgetId);
            }
        }

        $layout['widths'] = $widths;
        $layout['navigation'] = [
            'parts_help_page_id' => isset($_POST['navigation']['parts_help_page_id']) ? (int) $_POST['navigation']['parts_help_page_id'] : 0,
            'team_score_page_id' => isset($_POST['navigation']['team_score_page_id']) ? (int) $_POST['navigation']['team_score_page_id'] : 0,
        ];

        return $layout;
    }

    /**
     * @return array<int, object>
     */
    private function listPublishedPages(): array {
        if (!function_exists('get_pages')) {
            return [];
        }

        $pages = get_pages([
            'post_status' => 'publish',
            'sort_column' => 'post_title',
            'sort_order' => 'asc',
        ]);

        return is_array($pages) ? $pages : [];
    }
}
