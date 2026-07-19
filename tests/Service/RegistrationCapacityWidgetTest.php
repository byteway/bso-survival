<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\RegistrationWindowRepositoryInterface;
use BSO\Survival\Service\RegistrationWindowService;
use BSO\Survival\Widgets\RegistrationCapacityWidget;
use PHPUnit\Framework\TestCase;

class RegistrationCapacityWidgetTest extends TestCase {
    /** @test */
    public function it_reports_open_capacity_with_remaining_places(): void {
        $widget = new RegistrationCapacityWidget(new RegistrationWindowService(new FakeRegistrationWindowRepository([1 => true])));

        $data = $widget->getData([
            'event' => (object) ['id' => 1],
            'counts' => [
                'registered_teams' => 10,
                'max_teams' => 30,
            ],
            'status' => [],
        ], [
            'event_id' => 1,
        ]);

        $this->assertSame(10, $data['registered']);
        $this->assertSame(30, $data['max_teams']);
        $this->assertSame(20, $data['remaining']);
        $this->assertSame(33, $data['utilization']);
        $this->assertSame('open', $data['status']);
        $this->assertSame('Open voor inschrijvingen', $data['status_label']);

        $html = $widget->render(['data' => $data]);
        $this->assertStringContainsString('10 / 30', $html);
        $this->assertStringContainsString('20 beschikbare plaatsen', $html);
        $this->assertStringContainsString('Open voor inschrijvingen', $html);
        $this->assertStringContainsString('bso-widget-registration-capacity--is-open', $html);
    }

    /** @test */
    public function it_reports_limited_capacity_when_only_few_places_remain(): void {
        $widget = new RegistrationCapacityWidget(new RegistrationWindowService(new FakeRegistrationWindowRepository([2 => true])));

        $data = $widget->getData([
            'event' => (object) ['id' => 2],
            'counts' => [
                'registered_teams' => 26,
                'max_teams' => 30,
            ],
            'status' => [],
        ], [
            'event_id' => 2,
        ]);

        $this->assertSame(4, $data['remaining']);
        $this->assertSame('limited', $data['status']);
        $this->assertSame('Beperkt aantal plaatsen beschikbaar', $data['status_label']);

        $html = $widget->render(['data' => $data]);
        $this->assertStringContainsString('26 / 30', $html);
        $this->assertStringContainsString('4 beschikbare plaatsen', $html);
        $this->assertStringContainsString('Beperkt', $html);
        $this->assertStringContainsString('bso-widget-registration-capacity--is-limited', $html);
    }

    /** @test */
    public function it_reports_full_capacity_when_maximum_is_reached(): void {
        $widget = new RegistrationCapacityWidget(new RegistrationWindowService(new FakeRegistrationWindowRepository([3 => true])));

        $data = $widget->getData([
            'event' => (object) ['id' => 3],
            'counts' => [
                'registered_teams' => 30,
                'max_teams' => 30,
            ],
            'status' => [],
        ], [
            'event_id' => 3,
        ]);

        $this->assertSame(0, $data['remaining']);
        $this->assertSame('full', $data['status']);
        $this->assertSame('Volgeboekt', $data['status_label']);

        $html = $widget->render(['data' => $data]);
        $this->assertStringContainsString('30 / 30', $html);
        $this->assertStringContainsString('VOL', $html);
        $this->assertStringContainsString('Volgeboekt', $html);
        $this->assertStringContainsString('bso-widget-registration-capacity--is-full', $html);
    }

    /** @test */
    public function it_reports_closed_capacity_when_registration_window_is_closed(): void {
        $widget = new RegistrationCapacityWidget(new RegistrationWindowService(new FakeRegistrationWindowRepository([4 => false])));

        $data = $widget->getData([
            'event' => (object) ['id' => 4],
            'counts' => [
                'registered_teams' => 12,
                'max_teams' => 30,
            ],
            'status' => [],
        ], [
            'event_id' => 4,
        ]);

        $this->assertSame('closed', $data['status']);
        $this->assertSame('Inschrijvingen gesloten', $data['status_label']);

        $html = $widget->render(['data' => $data]);
        $this->assertStringContainsString('12 / 30', $html);
        $this->assertStringContainsString('Inschrijvingen gesloten', $html);
        $this->assertStringContainsString('bso-widget-registration-capacity--is-closed', $html);
    }
}

class FakeRegistrationWindowRepository implements RegistrationWindowRepositoryInterface {
    /** @var array<int, bool> */
    private $openByEventId;

    /**
     * @param array<int, bool> $openByEventId
     */
    public function __construct(array $openByEventId) {
        $this->openByEventId = $openByEventId;
    }

    public function findOpenForEventAt(int $eventId, string $momentUtc) {
        return !empty($this->openByEventId[$eventId])
            ? (object) ['id' => $eventId, 'event_id' => $eventId, 'status' => 'open']
            : null;
    }

    public function findByEventId(int $eventId) {
        return (object) [
            'id' => $eventId,
            'event_id' => $eventId,
            'opens_at' => '2026-01-01 08:00:00',
            'closes_at' => '2026-01-01 10:00:00',
            'status' => !empty($this->openByEventId[$eventId]) ? 'open' : 'closed',
        ];
    }

    public function saveForEvent(int $eventId, string $opensAt, string $closesAt, string $status = 'open') {
        return $this->findByEventId($eventId);
    }
}
