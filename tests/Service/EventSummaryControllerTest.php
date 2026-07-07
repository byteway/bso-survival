<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Frontend\EventSummaryController;
use BSO\Survival\Service\DashboardOverviewService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EventSummaryControllerTest extends TestCase {
    /**
     * @test
     */
    public function it_renders_compact_event_summary(): void {
        $controller = new EventSummaryController(new FakeEventSummaryOverviewService());

        $output = $controller->render([
            'title' => 'Compact Overzicht',
            'event_id' => 2,
        ]);

        $this->assertStringContainsString('Compact Overzicht', $output);
        $this->assertStringContainsString('Event #2 - Test event', $output);
        $this->assertStringContainsString('Status: concept', $output);
        $this->assertStringContainsString('Onderdelen: 2', $output);
        $this->assertStringContainsString('Teams: 2', $output);
        $this->assertStringContainsString('Klaar voor planning: ja', $output);
    }

    /**
     * @test
     */
    public function it_handles_missing_event_without_fatal_error(): void {
        $controller = new EventSummaryController(new ThrowingEventSummaryOverviewService());

        $output = $controller->render([
            'event_id' => 99,
        ]);

        $this->assertStringContainsString('Compact overzicht niet beschikbaar voor event_id 99.', $output);
    }
}

class FakeEventSummaryOverviewService extends DashboardOverviewService {
    public function __construct() {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverviewForEvent(int $eventId): array {
        return [
            'event' => (object) [
                'id' => $eventId,
                'name' => 'Test event',
                'status' => 'concept',
            ],
            'parts' => [
                (object) ['name' => 'Kanovaren'],
                (object) ['name' => 'Touwbaan'],
            ],
            'teams' => [
                (object) ['name' => 'Team001'],
                (object) ['name' => 'Team002'],
            ],
            'counts' => [
                'parts' => 2,
                'teams' => 2,
            ],
            'status' => [
                'event_status' => 'concept',
                'has_parts' => true,
                'has_teams' => true,
                'is_ready_for_planning' => true,
            ],
        ];
    }
}

class ThrowingEventSummaryOverviewService extends DashboardOverviewService {
    public function __construct() {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverviewForEvent(int $eventId): array {
        throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
    }
}
