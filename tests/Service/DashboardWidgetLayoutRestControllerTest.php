<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Api\DashboardWidgetLayoutRestController;
use BSO\Survival\Database\Repository\DashboardWidgetLayoutRepositoryInterface;
use BSO\Survival\Service\DashboardWidgetLayoutService;
use BSO\Survival\Service\DashboardWidgetRegistry;
use PHPUnit\Framework\TestCase;

class DashboardWidgetLayoutRestControllerTest extends TestCase {
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
    public function it_returns_layout_for_event(): void {
        $service = new DashboardWidgetLayoutService(new InMemoryRestDashboardWidgetLayoutRepository());
        $service->saveLayoutForEvent(11, [
            'main' => ['team_ranking'],
            'operations' => ['message_widget'],
        ]);

        $controller = new DashboardWidgetLayoutRestController($service);
        $response = $controller->getLayout(new FakeRestRequest([
            'event_id' => 11,
        ]));

        $this->assertSame(11, $response['event_id']);
        $this->assertSame(['team_ranking'], $response['layout']['main']);
    }

    /**
     * @test
     */
    public function it_updates_layout_from_payload(): void {
        $service = new DashboardWidgetLayoutService(new InMemoryRestDashboardWidgetLayoutRepository());
        $controller = new DashboardWidgetLayoutRestController($service);

        $response = $controller->updateLayout(new FakeRestRequest([
            'event_id' => 12,
            'layout' => [
                'main' => ['reporting_status', 'team_ranking'],
                'operations' => ['contact_finder'],
            ],
        ]));

        $this->assertTrue($response['updated']);
        $this->assertSame(['reporting_status', 'team_ranking'], $response['layout']['main']);
        $this->assertSame(['contact_finder'], $response['layout']['operations']);
    }
}

class FakeRestRequest {
    /** @var array<string, mixed> */
    private $params;

    /**
     * @param array<string, mixed> $params
     */
    public function __construct(array $params) {
        $this->params = $params;
    }

    /**
     * @return mixed
     */
    public function get_param(string $key) {
        return $this->params[$key] ?? null;
    }
}

class InMemoryRestDashboardWidgetLayoutRepository implements DashboardWidgetLayoutRepositoryInterface {
    /** @var array<int, array<string, array<int, string>>> */
    private $store = [];

    public function getByEventId(int $eventId): array {
        return $this->store[$eventId] ?? [];
    }

    public function saveByEventId(int $eventId, array $layout): void {
        $this->store[$eventId] = $layout;
    }
}
