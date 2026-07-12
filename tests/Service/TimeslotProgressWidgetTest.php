<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Widgets\TimeslotProgressWidget;
use PHPUnit\Framework\TestCase;

class TimeslotProgressWidgetTest extends TestCase {
    protected function tearDown(): void {
        unset($GLOBALS['wpdb']);
    }

    /** @test */
    public function it_builds_segmented_progress_for_event_timeslots(): void {
        $GLOBALS['wpdb'] = new FakeTimeslotWidgetWpdb([
            (object) ['id' => 1, 'start_at' => '2026-07-11 09:00:00', 'end_at' => '2026-07-11 09:30:00'],
            (object) ['id' => 2, 'start_at' => '2026-07-11 09:35:00', 'end_at' => '2026-07-11 10:05:00'],
            (object) ['id' => 3, 'start_at' => '2026-07-11 10:10:00', 'end_at' => '2026-07-11 10:40:00'],
        ]);

        $widget = new TimeslotProgressWidget(static function (): int {
            return strtotime('2026-07-11 09:50:00 UTC');
        });

        $data = $widget->getData([
            'event' => (object) ['id' => 7],
        ]);

        $this->assertSame(50, $data['total_progress']);
        $this->assertSame(50, $data['current_slot_progress']);
        $this->assertSame('Tijdslot 2 actief', $data['current_slot_label']);
        $this->assertCount(3, $data['segments']);
        $this->assertSame('completed', $data['segments'][0]['state']);
        $this->assertSame('active', $data['segments'][1]['state']);
        $this->assertSame('pending', $data['segments'][2]['state']);

        $html = $widget->render(['data' => $data]);
        $this->assertStringContainsString('50%', $html);
        $this->assertStringContainsString('50%', $html);
        $this->assertStringContainsString('bso-timeslot-segment--active', $html);
        $this->assertStringContainsString('bso-timeslot-segment--completed', $html);
        $this->assertStringContainsString('bso-widget-timeslot__figure-title', $html);
        $this->assertStringContainsString('Tijdsloten', $html);
        $this->assertStringContainsString('>2<', $html);
        $this->assertStringContainsString('09:35 - 10:05', $html);
    }
}

class FakeTimeslotWidgetWpdb {
    public $prefix = 'wp_';

    /** @var array<int, object> */
    private $timeslots;

    /**
     * @param array<int, object> $timeslots
     */
    public function __construct(array $timeslots) {
        $this->timeslots = $timeslots;
    }

    public function prepare(string $query, ...$args): string {
        return $query;
    }

    /**
     * @return array<int, object>
     */
    public function get_results(string $sql): array {
        return $this->timeslots;
    }
}
