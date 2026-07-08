<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Frontend\EventOverviewController;
use BSO\Survival\Service\DashboardOverviewService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EventOverviewControllerTest extends TestCase {
    /**
     * @test
     */
    public function it_renders_combined_event_overview(): void {
        $controller = new EventOverviewController(new FakeEventOverviewService());

        $output = $controller->render([
            'title' => 'Gecombineerd overzicht',
            'event_id' => 2,
        ]);

        $this->assertStringContainsString('Gecombineerd overzicht', $output);
        $this->assertStringContainsString('Event #2 - Test event', $output);
        $this->assertStringContainsString('Status: concept', $output);
        $this->assertStringContainsString('Onderdelen: 2', $output);
        $this->assertStringContainsString('Teams: 2', $output);
        $this->assertStringContainsString('Kanovaren', $output);
        $this->assertStringContainsString('Team001', $output);
    }

    /**
     * @test
     */
    public function it_renders_compact_overview_without_detailed_lists(): void {
        $controller = new EventOverviewController(new FakeEventOverviewService());

        $output = $controller->render([
            'event_id' => 2,
            'compact' => 'yes',
        ]);

        $this->assertStringContainsString('Status: concept', $output);
        $this->assertStringContainsString('Onderdelen: 2', $output);
        $this->assertStringContainsString('Teams: 2', $output);
        $this->assertStringNotContainsString('Kanovaren', $output);
        $this->assertStringNotContainsString('Team001', $output);
        $this->assertStringNotContainsString('<h3>Onderdelen</h3>', $output);
        $this->assertStringNotContainsString('<h3>Teams</h3>', $output);
    }

    /**
     * @test
     */
    public function it_handles_missing_event_without_fatal_error(): void {
        $controller = new EventOverviewController(new ThrowingEventOverviewService());

        $output = $controller->render([
            'event_id' => 99,
        ]);

        $this->assertStringContainsString('Eventoverzicht niet beschikbaar voor event_id 99.', $output);
    }

    /**
     * @test
     */
    public function it_renders_read_only_and_publication_notices_for_published_events(): void {
        $controller = new EventOverviewController(new PublishedEventOverviewService());

        $output = $controller->render([
            'event_id' => 3,
        ]);

        $this->assertStringContainsString('Dit event is read-only afgesloten.', $output);
        $this->assertStringContainsString('De eindstand van dit event is gepubliceerd.', $output);
    }
}

class FakeEventOverviewService extends DashboardOverviewService {
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

class ThrowingEventOverviewService extends DashboardOverviewService {
    public function __construct() {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverviewForEvent(int $eventId): array {
        throw new InvalidArgumentException(sprintf('Event %d not found.', $eventId));
    }
}

class PublishedEventOverviewService extends DashboardOverviewService {
    public function __construct() {
    }

    public function getOverviewForEvent(int $eventId): array {
        return [
            'event' => (object) ['id' => $eventId, 'name' => 'Published event', 'status' => 'gepubliceerd'],
            'parts' => [],
            'teams' => [],
            'counts' => ['parts' => 0, 'teams' => 0],
            'status' => [
                'event_status' => 'gepubliceerd',
                'has_parts' => false,
                'has_teams' => false,
                'is_ready_for_planning' => false,
                'is_read_only' => true,
                'is_published' => true,
            ],
        ];
    }
}
