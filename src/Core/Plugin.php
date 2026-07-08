<?php

namespace BSO\Survival\Core;

use BSO\Survival\Admin\DashboardWidgetAdminPage;
use BSO\Survival\Admin\DashboardMessageAdminPage;
use BSO\Survival\Admin\EmailTemplateAdminPage;
use BSO\Survival\Admin\EventLifecycleAdminPage;
use BSO\Survival\Admin\PartRuleAdminPage;
use BSO\Survival\Admin\RegistrationAdminPage;
use BSO\Survival\Admin\ScoreEntryAdminPage;
use BSO\Survival\Api\AdminScoreRestController;
use BSO\Survival\Api\DashboardWidgetLayoutRestController;
use BSO\Survival\Api\EventCloseoutRestController;
use BSO\Survival\Api\FrontendScoreRestController;
use BSO\Survival\Api\TeamRegistrationRestController;
use BSO\Survival\Database\Repository\AssignmentRepository;
use BSO\Survival\Core\Cli\EventLifecycleCommand;
use BSO\Survival\Core\Cli\SeedGoldenDatasetCommand;
use BSO\Survival\Database\Repository\AuditLogRepository;
use BSO\Survival\Database\Repository\CertificateRepository;
use BSO\Survival\Database\Repository\DashboardMessageRepository;
use BSO\Survival\Database\Repository\DashboardWidgetLayoutRepository;
use BSO\Survival\Database\Repository\EmailOutboxRepository;
use BSO\Survival\Database\Repository\EmailTemplateRepository;
use BSO\Survival\Database\Repository\EventPublicationRepository;
use BSO\Survival\Database\Repository\EventRepository;
use BSO\Survival\Database\Repository\PartRepository;
use BSO\Survival\Database\Repository\PartRuleRepository;
use BSO\Survival\Database\Repository\RegistrationWindowRepository;
use BSO\Survival\Database\Repository\ScoreEntryRepository;
use BSO\Survival\Database\Repository\TeamMemberRepository;
use BSO\Survival\Database\Repository\TeamRepository;
use BSO\Survival\Frontend\ShortcodeController;
use BSO\Survival\Service\AuditLogService;
use BSO\Survival\Service\AdminScoreService;
use BSO\Survival\Service\DashboardMessageService;
use BSO\Survival\Service\CertificateService;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\DashboardWidgetLayoutService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use BSO\Survival\Service\EmailOutboxService;
use BSO\Survival\Service\EmailTemplateService;
use BSO\Survival\Service\EventCloseoutService;
use BSO\Survival\Service\EventPublicationService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\FrontendScoreSubmissionService;
use BSO\Survival\Service\OutboxProcessorService;
use BSO\Survival\Service\PartRuleConfiguratorService;
use BSO\Survival\Service\PartService;
use BSO\Survival\Service\PublicationNotificationService;
use BSO\Survival\Service\RegistrationConfirmationService;
use BSO\Survival\Service\ScoringMethodRegistry;
use BSO\Survival\Service\ScoreComputationService;
use BSO\Survival\Service\ScoreEntryService;
use BSO\Survival\Service\TeamService;
use BSO\Survival\Service\TeamRegistrationService;
use BSO\Survival\Service\WpMailer;
use BSO\Survival\Service\RegistrationWindowService;
use BSO\Survival\Service\RankingService;

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
        add_action('admin_post_bso_survival_save_email_template', [$this, 'handle_email_template_save']);
        add_action('admin_post_bso_survival_admin_score_create', [$this, 'handle_admin_score_create']);
        add_action('admin_post_bso_survival_admin_score_update', [$this, 'handle_admin_score_update']);
        add_action('admin_post_bso_survival_dashboard_message_create', [$this, 'handle_dashboard_message_create']);
        add_action('admin_post_bso_survival_dashboard_message_toggle', [$this, 'handle_dashboard_message_toggle']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('init', [$this, 'schedule_email_outbox_processing']);
        add_action('bso_survival_process_email_outbox', [$this, 'process_email_outbox']);
        add_action('admin_notices', [$this, 'render_dashboard_admin_notice']);
        add_action('bso_survival_dashboard_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);
        add_action('bso_survival_parts_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);
        add_action('bso_survival_teams_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);
        add_action('bso_survival_event_overview_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);
        add_action('bso_survival_event_summary_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);
        add_action('bso_survival_score_form_render_error', [$this, 'capture_dashboard_render_error'], 10, 2);

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
        $this->buildRegistrationAdminPage()->registerMenu();
        $this->buildScoreEntryAdminPage()->registerMenu();
        $this->buildDashboardMessageAdminPage()->registerMenu();
        $this->buildEventLifecycleAdminPage()->registerMenu();
        $this->buildEmailTemplateAdminPage()->registerMenu();
    }

    public function handle_part_rule_save(): void {
        $this->buildPartRuleAdminPage()->handleSave();
    }

    public function handle_dashboard_widget_save(): void {
        $this->buildDashboardWidgetAdminPage()->handleSave();
    }

    public function handle_email_template_save(): void {
        $this->buildEmailTemplateAdminPage()->handleSave();
    }

    public function handle_admin_score_create(): void {
        $this->buildScoreEntryAdminPage()->handleCreate();
    }

    public function handle_admin_score_update(): void {
        $this->buildScoreEntryAdminPage()->handleUpdate();
    }

    public function handle_dashboard_message_create(): void {
        $this->buildDashboardMessageAdminPage()->handleCreate();
    }

    public function handle_dashboard_message_toggle(): void {
        $this->buildDashboardMessageAdminPage()->handleToggle();
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

        wp_register_script(
            'bso-survival-team-registration',
            plugins_url('assets/js/bso-survival-team-registration.js', __DIR__ . '/../../bso-survival.php'),
            ['bso-survival-frontend'],
            '2.0.0',
            true
        );

        wp_register_script(
            'bso-survival-frontend-score',
            plugins_url('assets/js/bso-survival-frontend-score.js', __DIR__ . '/../../bso-survival.php'),
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

        wp_register_script(
            'bso-survival-admin-event-lifecycle',
            plugins_url('assets/js/bso-survival-event-lifecycle-admin.js', __DIR__ . '/../../bso-survival.php'),
            [],
            '2.0.0',
            true
        );

        wp_register_style(
            'bso-survival-part-rules-admin',
            plugins_url('assets/css/bso-survival-part-rules-admin.css', __DIR__ . '/../../bso-survival.php'),
            [],
            '2.0.0'
        );
    }

    public function register_rest_routes(): void {
        $this->buildDashboardWidgetLayoutRestController()->registerRoutes();
        $this->buildEventCloseoutRestController()->registerRoutes();
        $this->buildTeamRegistrationRestController()->registerRoutes();
        $this->buildFrontendScoreRestController()->registerRoutes();
        $this->buildAdminScoreRestController()->registerRoutes();
    }

    public function schedule_email_outbox_processing(): void {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        $hook = 'bso_survival_process_email_outbox';
        if (wp_next_scheduled($hook)) {
            return;
        }

        wp_schedule_event(time() + 60, 'hourly', $hook);
    }

    public function process_email_outbox(): void {
        $processor = $this->buildOutboxProcessorService();
        $processor->processDue(200);
    }

    private function register_cli_commands(): void {
        if (!defined('WP_CLI') || WP_CLI !== true || !class_exists('WP_CLI')) {
            return;
        }

        if (class_exists(SeedGoldenDatasetCommand::class)) {
            \WP_CLI::add_command('bso-survival seed-golden', SeedGoldenDatasetCommand::class);
        }

        if (class_exists(EventLifecycleCommand::class)) {
            \WP_CLI::add_command('bso-survival lifecycle', EventLifecycleCommand::class);
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

    private function buildRegistrationAdminPage(): RegistrationAdminPage {
        $eventService = new EventService(new EventRepository());
        $teamService = new TeamService(new TeamRepository());
        $windowService = new RegistrationWindowService(new RegistrationWindowRepository());

        return new RegistrationAdminPage($eventService, $teamService, $windowService);
    }

    private function buildScoreEntryAdminPage(): ScoreEntryAdminPage {
        $eventService = new EventService(new EventRepository());

        return new ScoreEntryAdminPage(
            $eventService,
            new AssignmentRepository(),
            $this->buildAdminScoreService()
        );
    }

    private function buildDashboardMessageAdminPage(): DashboardMessageAdminPage {
        $eventService = new EventService(new EventRepository());
        $messages = new DashboardMessageService(
            new DashboardMessageRepository(),
            new AuditLogService(new AuditLogRepository())
        );

        return new DashboardMessageAdminPage($eventService, $messages);
    }

    private function buildEventLifecycleAdminPage(): EventLifecycleAdminPage {
        $eventService = new EventService(new EventRepository());
        $publicationService = new EventPublicationService(new EventPublicationRepository());

        return new EventLifecycleAdminPage($eventService, $publicationService);
    }

    private function buildEmailTemplateAdminPage(): EmailTemplateAdminPage {
        return new EmailTemplateAdminPage(
            new EmailTemplateService(new EmailTemplateRepository()),
            new EmailOutboxService(new EmailOutboxRepository())
        );
    }

    private function buildDashboardWidgetLayoutRestController(): DashboardWidgetLayoutRestController {
        $layoutService = new DashboardWidgetLayoutService(new DashboardWidgetLayoutRepository());

        return new DashboardWidgetLayoutRestController($layoutService);
    }

    private function buildEventCloseoutRestController(): EventCloseoutRestController {
        $eventService = new EventService(new EventRepository());
        $certificateService = new CertificateService(new CertificateRepository());
        $auditLogService = new AuditLogService(new AuditLogRepository());
        $notificationService = $this->buildPublicationNotificationService();
        $publicationService = new EventPublicationService(new EventPublicationRepository());
        $closeoutService = new EventCloseoutService($eventService, $certificateService, $auditLogService, $notificationService, $publicationService);

        return new EventCloseoutRestController($closeoutService, $publicationService);
    }

    private function buildPublicationNotificationService(): PublicationNotificationService {
        $templateService = new EmailTemplateService(new EmailTemplateRepository());
        $outboxService = new EmailOutboxService(new EmailOutboxRepository());
        $processorService = $this->buildOutboxProcessorService($outboxService);

        return new PublicationNotificationService($templateService, $outboxService, $processorService);
    }

    private function buildOutboxProcessorService(EmailOutboxService $outboxService = null): OutboxProcessorService {
        $outbox = $outboxService ?? new EmailOutboxService(new EmailOutboxRepository());
        return new OutboxProcessorService($outbox, new WpMailer());
    }

    private function buildTeamRegistrationRestController(): TeamRegistrationRestController {
        $eventService = new EventService(new EventRepository());
        $teamRepository = new TeamRepository();
        $teamMemberRepository = new TeamMemberRepository();
        $registrationWindows = new RegistrationWindowRepository();
        $confirmationService = new RegistrationConfirmationService(
            new EmailTemplateService(new EmailTemplateRepository()),
            new EmailOutboxService(new EmailOutboxRepository())
        );

        $registrationService = new TeamRegistrationService(
            $eventService,
            $teamRepository,
            $teamMemberRepository,
            $registrationWindows,
            $confirmationService
        );

        return new TeamRegistrationRestController($registrationService);
    }

    private function buildFrontendScoreRestController(): FrontendScoreRestController {
        $eventService = new EventService(new EventRepository());
        $partService = new PartService(new PartRepository());
        $teamService = new TeamService(new TeamRepository());
        $publicationService = new EventPublicationService(new EventPublicationRepository());
        $overviewService = new DashboardOverviewService($eventService, $partService, $teamService, $publicationService);

        $scoreService = new FrontendScoreSubmissionService(
            $overviewService,
            new AssignmentRepository(),
            new ScoreEntryService(
                new ScoreEntryRepository(),
                new ScoreComputationService(new PartRuleRepository())
            )
        );

        return new FrontendScoreRestController($scoreService);
    }

    private function buildAdminScoreRestController(): AdminScoreRestController {
        return new AdminScoreRestController($this->buildAdminScoreService());
    }

    private function buildAdminScoreService(): AdminScoreService {
        $eventService = new EventService(new EventRepository());
        $partService = new PartService(new PartRepository());
        $teamService = new TeamService(new TeamRepository());
        $publicationService = new EventPublicationService(new EventPublicationRepository());
        $overviewService = new DashboardOverviewService($eventService, $partService, $teamService, $publicationService);
        $entries = new ScoreEntryRepository();
        $scoring = new ScoreComputationService(new PartRuleRepository());
        $scoreEntryService = new ScoreEntryService($entries, $scoring);
        $ranking = new RankingService($scoring);
        $audit = new AuditLogService(new AuditLogRepository());

        return new AdminScoreService(
            $overviewService,
            new AssignmentRepository(),
            $entries,
            $scoreEntryService,
            $ranking,
            $audit
        );
    }
}
