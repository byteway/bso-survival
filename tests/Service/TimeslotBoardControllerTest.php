<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Frontend\TimeslotBoardController;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\InterimTeamScoreService;
use BSO\Survival\Service\PartService;
use PHPUnit\Framework\TestCase;

class TimeslotBoardControllerTest extends TestCase {
    /**
     * @test
     */
    public function it_renders_timeslot_board_with_part_selector_and_status_leds(): void {
        set_test_queried_object_id(85);

        $controller = new TimeslotBoardController(new FakeEventService(), new FakePartService(), new FakeTimeslotScoreService());

        $output = $controller->render([
            'title' => 'Tijdslot test',
            'event_id' => 2,
            'part_id' => 7,
        ]);

        $this->assertStringContainsString('Tijdslot test', $output);
        $this->assertStringContainsString('Geselecteerd onderdeel: Kanovaren', $output);
        $this->assertStringContainsString('<option value="7" selected="selected">Kanovaren</option>', $output);
        $this->assertStringContainsString('<input type="hidden" name="page_id" value="85" />', $output);
        $this->assertStringContainsString('Tijdslot 1', $output);
        $this->assertStringContainsString('Team Rood', $output);
        $this->assertStringContainsString('Team Blauw', $output);
        $this->assertStringContainsString('bso-survival-timeslot-board__led is-on', $output);
        $this->assertStringContainsString('bso-survival-timeslot-board__led is-off', $output);
    }

    /**
     * @test
     */
    public function it_falls_back_when_the_event_is_missing(): void {
        $controller = new TimeslotBoardController(new MissingEventService(), new FakePartService(), new FakeTimeslotScoreService());

        $output = $controller->render([
            'event_id' => 99,
        ]);

        $this->assertStringContainsString('Tijdslot overzicht niet beschikbaar voor event_id 99.', $output);
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
            (object) ['id' => 7, 'name' => 'Kanovaren'],
            (object) ['id' => 9, 'name' => 'Touwbaan'],
        ];
    }
}

class FakeTimeslotScoreService extends InterimTeamScoreService {
    public function __construct() {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTimeslotBoardRows(int $eventId, int $partId): array {
        return [
            [
                'timeslot_id' => 11,
                'assignment_id' => 101,
                'part_name' => 'Kanovaren',
                'team_id' => 1,
                'team_name' => 'Team Blauw',
                'score_entry_id' => 0,
                'entered_by_role' => 'admin_init',
                'is_completed' => false,
            ],
            [
                'timeslot_id' => 11,
                'assignment_id' => 102,
                'part_name' => 'Kanovaren',
                'team_id' => 2,
                'team_name' => 'Team Rood',
                'score_entry_id' => 15,
                'entered_by_role' => 'frontend_jury',
                'is_completed' => true,
            ],
        ];
    }
}
