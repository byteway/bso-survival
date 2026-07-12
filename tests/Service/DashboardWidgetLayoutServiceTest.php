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

        $this->assertSame(['timeslot_progress', 'message_widget', 'registration_capacity', 'team_ranking', 'reporting_status'], $layout['main']);
        $this->assertSame(['contact_finder', 'fallback_score'], $layout['operations']);
        $this->assertSame('1', $layout['widths']['main']['timeslot_progress']);
        $this->assertSame('1', $layout['widths']['main']['message_widget']);
        $this->assertSame('1/4', $layout['widths']['main']['team_ranking']);
        $this->assertSame(1, DashboardWidgetLayoutService::widthToSpan('1/4'));
        $this->assertSame(2, DashboardWidgetLayoutService::widthToSpan('1/5'));
        $this->assertSame(3, DashboardWidgetLayoutService::widthToSpan('3/4'));
        $this->assertSame(4, DashboardWidgetLayoutService::widthToSpan('1'));

        $options = DashboardWidgetLayoutService::getWidthOptions();
        $this->assertSame('1 kolom', $options[0]['label']);
        $this->assertSame('2 kolommen', $options[1]['label']);
        $this->assertSame('3 kolommen', $options[2]['label']);
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

        $this->assertSame(['team_ranking', 'message_widget'], $saved['main']);
        $this->assertSame([], $saved['operations']);
        $this->assertSame($saved, $repository->getByEventId(3));
        $this->assertSame('1/4', $saved['widths']['main']['team_ranking']);
        $this->assertSame('1', $saved['widths']['main']['message_widget']);
    }

    /**
     * @test
     */
    public function it_migrates_legacy_operations_message_widget_to_main(): void {
        $repository = new InMemoryDashboardWidgetLayoutRepository();
        $service = new DashboardWidgetLayoutService($repository);

        $saved = $service->saveLayoutForEvent(4, [
            'main' => [],
            'operations' => ['message_widget'],
            'widths' => [
                'main' => ['timeslot_progress' => '3/4'],
                'operations' => ['message_widget' => '1'],
            ],
        ]);

        $this->assertSame(['message_widget'], $saved['main']);
        $this->assertSame([], $saved['operations']);
        $this->assertSame('3/4', $saved['widths']['main']['timeslot_progress']);
        $this->assertSame('1', $saved['widths']['main']['message_widget']);
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
