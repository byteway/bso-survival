<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Contracts\DashboardWidgetInterface;
use BSO\Survival\Service\DashboardWidgetRegistry;
use PHPUnit\Framework\TestCase;

class DashboardWidgetRegistryTest extends TestCase {
    protected function tearDown(): void {
        DashboardWidgetRegistry::reset();
        reset_test_current_user_caps();

        global $wp_actions;
        $wp_actions = [];
    }

    /**
     * @test
     */
    public function it_registers_default_widgets_for_main_and_operations_sections(): void {
        DashboardWidgetRegistry::initDefaults();

        $mainWidgets = DashboardWidgetRegistry::getSection('main');
        $operationsWidgets = DashboardWidgetRegistry::getSection('operations');

        $this->assertCount(5, $mainWidgets);
        $this->assertCount(2, $operationsWidgets);
        $this->assertNotNull(DashboardWidgetRegistry::get('main', 'timeslot_progress'));
        $this->assertNotNull(DashboardWidgetRegistry::get('main', 'message_widget'));
        $this->assertNotNull(DashboardWidgetRegistry::get('main', 'registration_capacity'));
        $this->assertNotNull(DashboardWidgetRegistry::get('main', 'team_ranking'));
        $this->assertNotNull(DashboardWidgetRegistry::get('main', 'reporting_status'));
        $this->assertNotNull(DashboardWidgetRegistry::get('operations', 'contact_finder'));
        $this->assertNotNull(DashboardWidgetRegistry::get('operations', 'fallback_score'));
    }

    /**
     * @test
     */
    public function it_renders_widgets_in_priority_order(): void {
        DashboardWidgetRegistry::initDefaults();

        $overview = [
            'event' => (object) ['id' => 1, 'name' => 'Test Event'],
            'parts' => [(object) ['name' => 'A']],
            'teams' => [(object) ['name' => 'Team A']],
            'counts' => ['parts' => 1, 'teams' => 1],
            'status' => ['has_parts' => true],
        ];

        $html = DashboardWidgetRegistry::renderSection('main', $overview);

        $this->assertStringContainsString('Tijdslot voortgang', $html);
        $this->assertStringContainsString('Inschrijfcapaciteit', $html);
        $this->assertStringContainsString('Teampositieoverzicht', $html);
        $this->assertTrue(strpos($html, 'Tijdslot voortgang') < strpos($html, 'Teampositieoverzicht'));
    }

    /**
     * @test
     */
    public function it_uses_persisted_publication_results_for_team_ranking_widget(): void {
        DashboardWidgetRegistry::initDefaults();

        $overview = [
            'event' => (object) ['id' => 7, 'name' => 'Published Event'],
            'parts' => [],
            'teams' => [
                (object) ['name' => 'Fallback Team 1'],
                (object) ['name' => 'Fallback Team 2'],
            ],
            'publication' => [
                'final_standings' => [
                    ['rank' => 1, 'team_name' => 'Team Rood', 'points' => 98.5],
                    ['rank' => 2, 'team_name' => 'Team Blauw', 'points' => 95.25],
                    ['rank' => 3, 'team_name' => 'Team Groen', 'points' => 92.0],
                ],
            ],
            'counts' => ['parts' => 0, 'teams' => 2],
            'status' => ['has_parts' => false],
        ];

        $html = DashboardWidgetRegistry::renderSection('main', $overview);

        $this->assertStringContainsString('#1 Team Rood (98.50 pt)', $html);
        $this->assertStringContainsString('#2 Team Blauw (95.25 pt)', $html);
        $this->assertStringNotContainsString('Fallback Team 1', $html);
    }

    /**
     * @test
     */
    public function it_aggregates_widget_dependencies_per_section(): void {
        DashboardWidgetRegistry::initDefaults();

        $styles = DashboardWidgetRegistry::getSectionStyleDependencies('main');
        $scripts = DashboardWidgetRegistry::getSectionScriptDependencies('main');

        $this->assertSame(['bso-survival-dashboard-widgets'], $styles);
        $this->assertSame(['bso-survival-dashboard-widgets'], $scripts);
    }

    /**
     * @test
     */
    public function it_filters_capability_bound_widgets_during_rendering(): void {
        set_test_current_user_caps([
            'read' => true,
            'manage_options' => false,
        ]);

        DashboardWidgetRegistry::initDefaults();

        $overview = [
            'event' => (object) ['id' => 2, 'name' => 'Capability Event'],
            'parts' => [],
            'teams' => [],
            'counts' => ['parts' => 0, 'teams' => 0],
            'status' => ['has_parts' => false],
        ];

        $html = DashboardWidgetRegistry::renderSection('operations', $overview);

        $this->assertStringContainsString('Contactzoeker', $html);
        $this->assertStringNotContainsString('Fallback-scoreinvoer', $html);
    }

    /**
     * @test
     */
    public function it_supports_custom_widget_registration_via_init_hook(): void {
        add_action('bso_survival_dashboard_widgets_init', function ($registryClass): void {
            $registryClass::register('operations', new TestCustomDashboardWidget());
        }, 10, 1);

        DashboardWidgetRegistry::initDefaults();

        $this->assertNotNull(DashboardWidgetRegistry::get('operations', 'custom_ops_widget'));

        $overview = [
            'event' => (object) ['id' => 9, 'name' => 'Hook Event'],
            'parts' => [],
            'teams' => [],
            'counts' => ['parts' => 0, 'teams' => 0],
            'status' => ['has_parts' => false],
        ];

        $html = DashboardWidgetRegistry::renderSection('operations', $overview);
        $this->assertStringContainsString('Custom operations widget', $html);
    }
}

class TestCustomDashboardWidget implements DashboardWidgetInterface {
    public function getId(): string { return 'custom_ops_widget'; }

    public function getTitle(): string { return 'Custom operations widget'; }

    public function getPriority(): int { return 15; }

    public function getCapabilities(): array { return ['read']; }

    public function getData(array $overview, array $filters = []): array { return ['ok' => true]; }

    public function render(array $context): string {
        return '<article><h3>' . esc_html($this->getTitle()) . '</h3></article>';
    }

    public function getScriptDependencies(): array { return []; }

    public function getStyleDependencies(): array { return []; }
}
