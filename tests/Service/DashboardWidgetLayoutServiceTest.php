<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\DashboardWidgetLayoutRepositoryInterface;
use BSO\Survival\Service\DashboardWidgetLayoutService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use PHPUnit\Framework\TestCase;

class DashboardWidgetLayoutServiceTest extends TestCase {
    protected function setUp(): void {
        DashboardWidgetRegistry::reset();
        DashboardWidgetRegistry::initDefaults();
    }

    protected function tearDown(): void {
        DashboardWidgetRegistry::reset();
    }

    /**
     * @test
     */
    public function it_returns_default_layout_when_nothing_is_saved(): void {
        $service = new DashboardWidgetLayoutService(new InMemoryDashboardWidgetLayoutRepository());

        $layout = $service->getLayoutForEvent(5);

        $this->assertSame(['timeslot_progress', 'registration_capacity', 'team_ranking', 'reporting_status'], $layout['main']);
        $this->assertSame(['message_widget', 'contact_finder', 'fallback_score'], $layout['operations']);
    }

    /**
     * @test
     */
    public function it_sanitizes_and_persists_layout_for_event(): void {
        $repository = new InMemoryDashboardWidgetLayoutRepository();
        $service = new DashboardWidgetLayoutService($repository);

        $saved = $service->saveLayoutForEvent(3, [
            'main' => ['team_ranking', 'unknown_widget', 'team_ranking'],
            'operations' => ['message_widget'],
        ]);

        $this->assertSame(['team_ranking'], $saved['main']);
        $this->assertSame(['message_widget'], $saved['operations']);
        $this->assertSame($saved, $repository->getByEventId(3));
    }

    /**
     * @test
     */
    public function it_keeps_explicitly_empty_section_selection(): void {
        $repository = new InMemoryDashboardWidgetLayoutRepository();
        $service = new DashboardWidgetLayoutService($repository);

        $saved = $service->saveLayoutForEvent(4, [
            'main' => [],
            'operations' => ['message_widget'],
        ]);

        $this->assertSame([], $saved['main']);
        $this->assertSame(['message_widget'], $saved['operations']);
    }
}

class InMemoryDashboardWidgetLayoutRepository implements DashboardWidgetLayoutRepositoryInterface {
    /** @var array<int, array<string, array<int, string>>> */
    private $store = [];

    public function getByEventId(int $eventId): array {
        return $this->store[$eventId] ?? [];
    }

    public function saveByEventId(int $eventId, array $layout): void {
        $this->store[$eventId] = $layout;
    }
}
