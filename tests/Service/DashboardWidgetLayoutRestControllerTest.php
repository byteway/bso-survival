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
        reset_test_current_user_caps();
        set_test_nonce_verification_result(1);
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

        $this->assertTrue($response['success']);
        $this->assertSame(11, $response['data']['event_id']);
        $this->assertSame(['team_ranking'], $response['data']['layout']['main']);
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

        $this->assertTrue($response['success']);
        $this->assertTrue($response['data']['updated']);
        $this->assertSame(['reporting_status', 'team_ranking'], $response['data']['layout']['main']);
        $this->assertSame(['contact_finder'], $response['data']['layout']['operations']);
    }

    /**
     * @test
     */
    public function it_denies_manage_without_rest_nonce(): void {
        set_test_current_user_caps([
            'manage_options' => true,
        ]);
        set_test_nonce_verification_result(false);

        $service = new DashboardWidgetLayoutService(new InMemoryRestDashboardWidgetLayoutRepository());
        $controller = new DashboardWidgetLayoutRestController($service);

        $request = new FakeRestRequest([
            'event_id' => 9,
        ], [
            'X-WP-Nonce' => '',
        ]);

        $this->assertFalse($controller->canManage($request));
    }

    /**
     * @test
     */
    public function it_allows_manage_with_valid_rest_nonce(): void {
        set_test_current_user_caps([
            'manage_options' => true,
        ]);
        set_test_nonce_verification_result(1);

        $service = new DashboardWidgetLayoutService(new InMemoryRestDashboardWidgetLayoutRepository());
        $controller = new DashboardWidgetLayoutRestController($service);

        $request = new FakeRestRequest([
            'event_id' => 9,
        ], [
            'X-WP-Nonce' => 'valid-nonce',
        ]);

        $this->assertTrue($controller->canManage($request));
    }
}

class FakeRestRequest {
    /** @var array<string, mixed> */
    private $params;

    /** @var array<string, string> */
    private $headers;

    /**
     * @param array<string, mixed> $params
     */
    public function __construct(array $params, array $headers = []) {
        $this->params = $params;
        $this->headers = $headers;
    }

    /**
     * @return mixed
     */
    public function get_param(string $key) {
        return $this->params[$key] ?? null;
    }

    public function get_header(string $key): string {
        return (string) ($this->headers[$key] ?? '');
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
