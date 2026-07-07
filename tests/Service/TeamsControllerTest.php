<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Frontend\TeamsController;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\TeamService;
use PHPUnit\Framework\TestCase;

class TeamsControllerTest extends TestCase {
    /**
     * @test
     */
    public function it_renders_read_only_team_list_for_event(): void {
        $controller = new TeamsController(new FakeTeamsEventService(), new FakeTeamsService());

        $output = $controller->render([
            'title' => 'Teams Test',
            'event_id' => 2,
        ]);

        $this->assertStringContainsString('Teams Test', $output);
        $this->assertStringContainsString('Event #2 - Test event', $output);
        $this->assertStringContainsString('Team001', $output);
        $this->assertStringContainsString('Team002', $output);
    }

    /**
     * @test
     */
    public function it_renders_empty_state_when_event_has_no_teams(): void {
        $controller = new TeamsController(new FakeTeamsEventService(), new EmptyTeamsService());

        $output = $controller->render([
            'event_id' => 2,
        ]);

        $this->assertStringContainsString('Geen teams gevonden voor dit event.', $output);
    }

    /**
     * @test
     */
    public function it_handles_missing_event_without_fatal_error(): void {
        $controller = new TeamsController(new MissingTeamsEventService(), new FakeTeamsService());

        $output = $controller->render([
            'event_id' => 99,
        ]);

        $this->assertStringContainsString('Teamlijst niet beschikbaar voor event_id 99.', $output);
    }
}

class FakeTeamsEventService extends EventService {
    public function __construct() {
    }

    /**
     * @return object|null
     */
    public function getEvent(int $id) {
        return (object) [
            'id' => $id,
            'name' => 'Test event',
        ];
    }
}

class MissingTeamsEventService extends EventService {
    public function __construct() {
    }

    /**
     * @return object|null
     */
    public function getEvent(int $id) {
        return null;
    }
}

class FakeTeamsService extends TeamService {
    public function __construct() {
    }

    /**
     * @return array<int, object>
     */
    public function listTeamsForEvent(int $eventId): array {
        return [
            (object) ['name' => 'Team001'],
            (object) ['name' => 'Team002'],
        ];
    }
}

class EmptyTeamsService extends TeamService {
    public function __construct() {
    }

    /**
     * @return array<int, object>
     */
    public function listTeamsForEvent(int $eventId): array {
        return [];
    }
}