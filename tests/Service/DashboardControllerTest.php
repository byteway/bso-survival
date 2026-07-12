<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Frontend\DashboardController;
use BSO\Survival\Service\DashboardWidgetLayoutService;
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
        $this->assertStringContainsString('Onderdelen', $output);
        $this->assertStringContainsString('Teams', $output);
        $this->assertStringContainsString('Klaar voor planning', $output);
        $this->assertStringContainsString('Kanovaren', $output);
        $this->assertStringContainsString('Team003', $output);
        $this->assertStringContainsString('Tijdslot voortgang', $output);
        $this->assertStringContainsString('Meldingen', $output);
        $this->assertTrue(strpos($output, 'Tijdslot voortgang') < strpos($output, 'Meldingen'));
    }

    /**
     * @test
     */
    public function it_falls_back_to_first_upcoming_active_event_when_event_id_is_missing(): void {
        $controller = new DashboardController(new FakeDashboardOverviewService());

        $output = $controller->render([
            'title' => 'Dashboard zonder event_id',
        ]);

        $this->assertStringContainsString('Dashboard zonder event_id', $output);
        $this->assertStringContainsString('Event #9 - Dash Event', $output);
        $this->assertStringContainsString('name="event_id"', $output);
        $this->assertStringContainsString('value="9" selected="selected"', $output);
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

    /**
     * @test
     */
    public function it_applies_saved_layout_per_event_for_widget_visibility_and_order(): void {
        $layoutService = new DashboardWidgetLayoutService(
            new FakeDashboardWidgetLayoutRepository([
                7 => [
                    'main' => ['team_ranking'],
                    'operations' => ['message_widget'],
                    'widths' => [
                        'main' => ['team_ranking' => '3/4'],
                        'operations' => ['message_widget' => '1'],
                    ],
                ],
            ])
        );

        $controller = new DashboardController(new FakeDashboardOverviewService(), $layoutService);

        $output = $controller->render([
            'title' => 'Dashboard layout test',
            'event_id' => 7,
        ]);

        $this->assertStringContainsString('Teampositieoverzicht', $output);
        $this->assertStringContainsString('Meldingen', $output);
        $this->assertStringNotContainsString('Tijdslot voortgang', $output);
        $this->assertStringContainsString('bso-survival-dashboard__widget--width-3-4', $output);
        $this->assertStringContainsString('bso-survival-dashboard__widget--width-1', $output);
    }

    /**
     * @test
     */
    public function it_moves_legacy_message_widget_from_operations_to_main_directly_after_timeslot(): void {
        $layoutService = new DashboardWidgetLayoutService(
            new FakeDashboardWidgetLayoutRepository([
                7 => [
                    'main' => ['timeslot_progress', 'team_ranking'],
                    'operations' => ['message_widget', 'contact_finder', 'fallback_score'],
                    'widths' => [
                        'main' => ['timeslot_progress' => '1', 'team_ranking' => '1/4'],
                        'operations' => ['message_widget' => '1', 'contact_finder' => '1/4', 'fallback_score' => '1/4'],
                    ],
                ],
            ])
        );

        $controller = new DashboardController(new FakeDashboardOverviewService(), $layoutService);

        $output = $controller->render([
            'title' => 'Dashboard legacy layout test',
            'event_id' => 7,
        ]);

        $this->assertStringContainsString('Tijdslot voortgang', $output);
        $this->assertStringContainsString('Meldingen', $output);
        $this->assertStringContainsString('Contactzoeker', $output);
        $this->assertTrue(strpos($output, 'Tijdslot voortgang') < strpos($output, 'Meldingen'));
        $this->assertTrue(strpos($output, 'Meldingen') < strpos($output, 'Contactzoeker'));
        $this->assertSame(1, substr_count($output, '<h3>Meldingen</h3>'));
    }

    /**
     * @test
     */
    public function it_hides_operational_widgets_for_read_only_events(): void {
        $controller = new DashboardController(new ClosedDashboardOverviewService());

        $output = $controller->render([
            'title' => 'Gesloten dashboard',
            'event_id' => 8,
        ]);

        $this->assertStringContainsString('Dit event is read-only afgesloten.', $output);
        $this->assertStringContainsString('Meldingen', $output);
        $this->assertStringNotContainsString('Contactzoeker', $output);
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

    /**
     * @return array<int, object>
     */
    public function listUpcomingActiveEvents(int $limit = 5): array {
        return array_slice([
            (object) ['id' => 9, 'name' => 'Actief Event Vandaag', 'event_date' => gmdate('Y-m-d')],
            (object) ['id' => 12, 'name' => 'Actief Event Morgen', 'event_date' => gmdate('Y-m-d', strtotime('+1 day'))],
        ], 0, $limit);
    }

    public function resolveDefaultDashboardEventId(): int {
        return 9;
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

    /**
     * @return array<int, object>
     */
    public function listUpcomingActiveEvents(int $limit = 5): array {
        return [];
    }

    public function resolveDefaultDashboardEventId(): int {
        return 0;
    }
}

class ClosedDashboardOverviewService extends DashboardOverviewService {
    public function __construct() {
    }

    public function getOverviewForEvent(int $eventId): array {
        return [
            'event' => (object) ['id' => $eventId, 'name' => 'Closed Event', 'status' => 'gepubliceerd'],
            'parts' => [(object) ['name' => 'Kanovaren']],
            'teams' => [(object) ['name' => 'Team001']],
            'counts' => ['parts' => 1, 'teams' => 1],
            'status' => [
                'event_status' => 'gepubliceerd',
                'has_parts' => true,
                'has_teams' => true,
                'is_ready_for_planning' => true,
                'is_read_only' => true,
                'is_published' => true,
            ],
        ];
    }

    /**
     * @return array<int, object>
     */
    public function listUpcomingActiveEvents(int $limit = 5): array {
        return array_slice([
            (object) ['id' => 8, 'name' => 'Closed Event', 'event_date' => gmdate('Y-m-d')],
        ], 0, $limit);
    }

    public function resolveDefaultDashboardEventId(): int {
        return 8;
    }
}

class FakeDashboardWidgetLayoutRepository implements \BSO\Survival\Database\Repository\DashboardWidgetLayoutRepositoryInterface {
    /** @var array<int, array<string, array<int, string>>> */
    private $layouts;

    /**
     * @param array<int, array<string, array<int, string>>> $layouts
     */
    public function __construct(array $layouts = []) {
        $this->layouts = $layouts;
    }

    public function getByEventId(int $eventId): array {
        return $this->layouts[$eventId] ?? [];
    }

    public function saveByEventId(int $eventId, array $layout): void {
        $this->layouts[$eventId] = $layout;
    }
}
