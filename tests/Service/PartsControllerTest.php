<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Frontend\PartsController;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PartsControllerTest extends TestCase {
    /**
     * @test
     */
    public function it_renders_read_only_parts_list_for_event(): void {
        $controller = new PartsController(new FakeEventService(), new FakePartService());

        $output = $controller->render([
            'title' => 'Onderdelen Test',
            'event_id' => 2,
        ]);

        $this->assertStringContainsString('Onderdelen Test', $output);
        $this->assertStringContainsString('Event #2 - Test event', $output);
        $this->assertStringContainsString('Kanovaren', $output);
        $this->assertStringContainsString('Touwbaan', $output);
    }

    /**
     * @test
     */
    public function it_renders_empty_state_when_event_has_no_parts(): void {
        $controller = new PartsController(new FakeEventService(), new EmptyPartService());

        $output = $controller->render([
            'event_id' => 2,
        ]);

        $this->assertStringContainsString('Geen onderdelen gevonden voor dit event.', $output);
    }

    /**
     * @test
     */
    public function it_handles_missing_event_without_fatal_error(): void {
        $controller = new PartsController(new MissingEventService(), new FakePartService());

        $output = $controller->render([
            'event_id' => 99,
        ]);

        $this->assertStringContainsString('Onderdelenlijst niet beschikbaar voor event_id 99.', $output);
    }
}

class FakeEventService extends EventService {
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

class MissingEventService extends EventService {
    public function __construct() {
    }

    /**
     * @return object|null
     */
    public function getEvent(int $id) {
        return null;
    }
}

class FakePartService extends PartService {
    public function __construct() {
    }

    /**
     * @return array<int, object>
     */
    public function listPartsForEvent(int $eventId): array {
        return [
            (object) ['name' => 'Kanovaren'],
            (object) ['name' => 'Touwbaan'],
        ];
    }
}

class EmptyPartService extends PartService {
    public function __construct() {
    }

    /**
     * @return array<int, object>
     */
    public function listPartsForEvent(int $eventId): array {
        return [];
    }
}