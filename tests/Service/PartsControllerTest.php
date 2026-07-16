<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Frontend\PartsController;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartHelpService;
use BSO\Survival\Service\PartService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PartsControllerTest extends TestCase {
    /**
     * @test
     */
    public function it_renders_read_only_parts_list_for_event(): void {
        $controller = new PartsController(new FakeEventService(), new FakePartService(), new FakePartHelpService());

        $output = $controller->render([
            'title' => 'Onderdelen Test',
            'event_id' => 2,
        ]);

        $this->assertStringContainsString('Onderdelen Test', $output);
        $this->assertStringContainsString('Event #2 - Test event', $output);
        $this->assertStringContainsString('Kanovaren', $output);
        $this->assertStringContainsString('Touwbaan', $output);
        $this->assertStringContainsString('Help voor Kanovaren', $output);
    }

    /**
     * @test
     */
    public function it_renders_empty_state_when_event_has_no_parts(): void {
        $controller = new PartsController(new FakeEventService(), new EmptyPartService(), new FakePartHelpService());

        $output = $controller->render([
            'event_id' => 2,
        ]);

        $this->assertStringContainsString('Geen onderdelen beschikbaar voor de helpweergave.', $output);
    }

    /**
     * @test
     */
    public function it_handles_missing_event_without_fatal_error(): void {
        $controller = new PartsController(new MissingEventService(), new FakePartService(), new FakePartHelpService());

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
            (object) ['id' => 11, 'name' => 'Kanovaren', 'latitude' => '52.1000', 'longitude' => '5.1000'],
            (object) ['id' => 12, 'name' => 'Touwbaan', 'latitude' => '52.2000', 'longitude' => '5.2000'],
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

class FakePartHelpService extends PartHelpService {
    public function __construct() {
    }

    /**
     * @param object $part
     * @return array{html:string, images:array<int, string>, context:array<string, string>}
     */
    public function renderForPart($part): array {
        return [
            'html' => '<p>Help voor ' . (string) ($part->name ?? '') . '</p>',
            'images' => [],
            'context' => [],
        ];
    }
}