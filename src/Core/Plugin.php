<?php

namespace BSO\Survival\Core;

use BSO\Survival\Admin\DashboardWidgetAdminPage;
use BSO\Survival\Admin\PartRuleAdminPage;
use BSO\Survival\Api\DashboardWidgetLayoutRestController;
use BSO\Survival\Core\Cli\SeedGoldenDatasetCommand;
use BSO\Survival\Database\Repository\DashboardWidgetLayoutRepository;
use BSO\Survival\Database\Repository\EventRepository;
use BSO\Survival\Database\Repository\PartRuleRepository;
use BSO\Survival\Frontend\ShortcodeController;
use BSO\Survival\Service\DashboardWidgetLayoutService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartRuleConfiguratorService;
use BSO\Survival\Service\ScoringMethodRegistry;

class Plugin {
    private const DASHBOARD_NOTICE_TRANSIENT = 'bso_survival_dashboard_admin_notice';

    public function register(): void {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('plugins_loaded', [$this, 'boot_scoring_methods'], 20);
        add_action('plugins_loaded', [$this, 'boot_dashboard_widgets'], 25);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('init', [$this, 'register_assets']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('admin_enqueue_scripts', [$this, 'register_assets']);
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_post_bso_survival_save_part_rule', [$this, 'handle_part_rule_save']);
        add_action('admin_post_bso_survival_save_dashboard_widgets', [$this, 'handle_dashboard_widget_save']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_notices', [$this, 'render_dashboard_admin_notice']);
        add_action('bso_survival_dashboard_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);
        add_action('bso_survival_parts_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);
        add_action('bso_survival_teams_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);
        add_action('bso_survival_event_overview_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);
        add_action('bso_survival_event_summary_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);

        $this->register_cli_commands();
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'bso-survival',
            false,
            dirname(plugin_basename(__DIR__ . '/../../bso-survival.php')) . '/languages'
        );
    }

    public function register_shortcodes(): void {
        (new ShortcodeController())->register();
    }

    public function boot_scoring_methods(): void {
        ScoringMethodRegistry::initDefaults();
    }

    public function boot_dashboard_widgets(): void {
        DashboardWidgetRegistry::initDefaults();
    }

    public function register_admin_pages(): void {
        $this->buildPartRuleAdminPage()->registerMenu();
        $this->buildDashboardWidgetAdminPage()->registerMenu();
    }

    public function handle_part_rule_save(): void {
        $this->buildPartRuleAdminPage()->handleSave();
    }

    public function handle_dashboard_widget_save(): void {
        $this->buildDashboardWidgetAdminPage()->handleSave();
    }

    public function register_assets(): void {
        wp_register_style(
            'bso-survival-frontend',
            plugins_url('assets/css/bso-survival.css', __DIR__ . '/../../bso-survival.php'),
            [],
            '2.0.0'
        );

        wp_register_script(
            'bso-survival-frontend',
            plugins_url('assets/js/bso-survival.js', __DIR__ . '/../../bso-survival.php'),
            [],
            '2.0.0',
            true
        );

        wp_register_style(
            'bso-survival-dashboard-widgets',
            plugins_url('assets/css/bso-survival-dashboard-widgets.css', __DIR__ . '/../../bso-survival.php'),
            ['bso-survival-frontend'],
            '2.0.0'
        );

        wp_register_script(
            'bso-survival-dashboard-widgets',
            plugins_url('assets/js/bso-survival-dashboard-widgets.js', __DIR__ . '/../../bso-survival.php'),
            ['bso-survival-frontend'],
            '2.0.0',
            true
        );

        wp_register_style(
            'bso-survival-admin-dashboard-widgets',
            plugins_url('assets/css/bso-survival-admin-dashboard-widgets.css', __DIR__ . '/../../bso-survival.php'),
            [],
            '2.0.0'
        );

        wp_register_script(
            'bso-survival-admin-dashboard-widgets',
            plugins_url('assets/js/bso-survival-admin-dashboard-widgets.js', __DIR__ . '/../../bso-survival.php'),
            [],
            '2.0.0',
            true
        );
    }

    public function register_rest_routes(): void {
        $this->buildDashboardWidgetLayoutRestController()->registerRoutes();
    }

    private function register_cli_commands(): void {
        if (!defined('WP_CLI') || WP_CLI !== true || !class_exists('WP_CLI')) {
            return;
        }

        if (class_exists(SeedGoldenDatasetCommand::class)) {
            \WP_CLI::add_command('bso-survival seed-golden', SeedGoldenDatasetCommand::class);
        }
    }

    public function capture_dashboard_render_error(string $message, int $eventId): void {
        if (!function_exists('is_admin') || !is_admin()) {
            return;
        }

        if (function_exists('set_transient')) {
            set_transient(
                self::DASHBOARD_NOTICE_TRANSIENT,
                sprintf('%s (event_id=%d)', $message, $eventId),
                120
            );
        }
    }

    public function render_dashboard_admin_notice(): void {
        if (!function_exists('get_transient') || !function_exists('delete_transient')) {
            return;
        }

        $message = get_transient(self::DASHBOARD_NOTICE_TRANSIENT);
        if (!is_string($message) || $message === '') {
            return;
        }

        delete_transient(self::DASHBOARD_NOTICE_TRANSIENT);
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private function buildPartRuleAdminPage(): PartRuleAdminPage {
        $eventService = new EventService(new EventRepository());
        $rules = new PartRuleRepository();
        $configurator = new PartRuleConfiguratorService($rules);

        return new PartRuleAdminPage($eventService, $configurator, $rules);
    }

    private function buildDashboardWidgetAdminPage(): DashboardWidgetAdminPage {
        $eventService = new EventService(new EventRepository());
        $layoutService = new DashboardWidgetLayoutService(new DashboardWidgetLayoutRepository());

        return new DashboardWidgetAdminPage($eventService, $layoutService);
    }

    private function buildDashboardWidgetLayoutRestController(): DashboardWidgetLayoutRestController {
        $layoutService = new DashboardWidgetLayoutService(new DashboardWidgetLayoutRepository());

        return new DashboardWidgetLayoutRestController($layoutService);
    }
}
