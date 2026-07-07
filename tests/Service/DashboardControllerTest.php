<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Frontend\DashboardController;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DashboardControllerTest extends TestCase {
    protected function tearDown(): void {
        DashboardWidgetRegistry::reset();
    }

    /**
     * @test
     */
    public function it_renders_read_only_dashboard_overview(): void {
        $controller = new DashboardController(new FakeDashboardOverviewService());

        $output = $controller->render([
            'title' => 'Dashboard titel',
            'event_id' => 7,
        ]);

        $this->assertStringContainsString('Dashboard titel', $output);
        $this->assertStringContainsString('Event #7 - Dash Event', $output);
        $this->assertStringContainsString('Onderdelen: 2', $output);
        $this->assertStringContainsString('Teams: 3', $output);
        $this->assertStringContainsString('Klaar voor planning: ja', $output);
        $this->assertStringContainsString('Kanovaren', $output);
        $this->assertStringContainsString('Team003', $output);
        $this->assertStringContainsString('Tijdslot voortgang', $output);
        $this->assertStringContainsString('Meldingen', $output);
    }

    /**
     * @test
     */
    public function it_handles_missing_event_without_fatal_error(): void {
        $controller = new DashboardController(new ThrowingDashboardOverviewService());

        $output = $controller->render([
            'event_id' => 2,
        ]);

        $this->assertStringContainsString('Dashboard niet beschikbaar voor event_id 2.', $output);
    }
}

class FakeDashboardOverviewService extends DashboardOverviewService {
    public function __construct() {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverviewForEvent(int $eventId): array {
        return [
            'event' => (object) [
                'id' => $eventId,
                'name' => 'Dash Event',
                'status' => 'gepland',
            ],
            'parts' => [
                (object) ['name' => 'Kanovaren'],
                (object) ['name' => 'Touwbaan'],
            ],
            'teams' => [
                (object) ['name' => 'Team001'],
                (object) ['name' => 'Team002'],
                (object) ['name' => 'Team003'],
            ],
            'counts' => [
                'parts' => 2,
                'teams' => 3,
            ],
            'status' => [
                'event_status' => 'gepland',
                'has_parts' => true,
                'has_teams' => true,
                'is_ready_for_planning' => true,
            ],
        ];
    }
}

class ThrowingDashboardOverviewService extends DashboardOverviewService {
    public function __construct() {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverviewForEvent(int $eventId): array {
        throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
    }
}