<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Api\DashboardMessageRestController;
use BSO\Survival\Service\DashboardMessageService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DashboardMessageRestControllerTest extends TestCase {
    protected function tearDown(): void {
        set_test_nonce_verification_result(1);
        reset_test_current_user_caps();
    }

    /** @test */
    public function it_lists_messages_with_scope_filter(): void {
        $service = new FakeDashboardMessageService();
        $controller = new DashboardMessageRestController($service);

        $response = $controller->listMessages(new FakeDashboardMessageRequest([
            'event_id' => 7,
            'scope' => 'global',
            'page' => 2,
            'per_page' => 10,
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame('global', $response['data']['scope']);
        $this->assertSame(1, count($response['data']['items']));
        $this->assertSame(2, $response['data']['pagination']['page']);
        $this->assertSame(10, $response['data']['pagination']['per_page']);
        $this->assertSame(42, $response['data']['pagination']['total']);
        $this->assertSame('global', $service->lastListScope);
    }

    /** @test */
    public function it_uses_legacy_limit_when_per_page_is_missing(): void {
        $service = new FakeDashboardMessageService();
        $controller = new DashboardMessageRestController($service);

        $response = $controller->listMessages(new FakeDashboardMessageRequest([
            'event_id' => 7,
            'scope' => 'event',
            'limit' => 15,
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame(1, $response['data']['pagination']['page']);
        $this->assertSame(15, $response['data']['pagination']['per_page']);
        $this->assertSame(15, $service->lastListPerPage);
    }

    /** @test */
    public function it_creates_message_via_rest(): void {
        $service = new FakeDashboardMessageService();
        $controller = new DashboardMessageRestController($service);

        $response = $controller->createMessage(new FakeDashboardMessageRequest([
            'event_id' => 7,
            'type' => 'warning',
            'text' => 'Let op',
            'scope' => 'event',
            'status' => 'actief',
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame('warning', $response['data']['item']->type);
        $this->assertSame('event', $service->lastCreateScope);
    }

    /** @test */
    public function it_supports_activate_endpoint(): void {
        $service = new FakeDashboardMessageService();
        $controller = new DashboardMessageRestController($service);

        $response = $controller->activateMessage(new FakeDashboardMessageRequest([
            'event_id' => 7,
            'message_id' => 11,
            'changed_by' => 'beheer',
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame('actief', $response['data']['item']->status);
        $this->assertSame('actief', $service->lastSetStatus);
    }

    /** @test */
    public function it_requires_manage_permission_and_valid_nonce(): void {
        set_test_current_user_caps(['manage_options' => false]);
        $controller = new DashboardMessageRestController(new FakeDashboardMessageService());
        $this->assertFalse($controller->canManage(new FakeDashboardMessageRequest([])));

        set_test_current_user_caps(['manage_options' => true]);
        set_test_nonce_verification_result(false);
        $this->assertFalse($controller->canManage(new FakeDashboardMessageRequest(['_header_nonce' => ''])));
    }
}

class FakeDashboardMessageService extends DashboardMessageService {
    /** @var string */
    public $lastListScope = 'all';

    /** @var string */
    public $lastCreateScope = 'event';

    /** @var string */
    public $lastSetStatus = '';

    /** @var int */
    public $lastListPage = 1;

    /** @var int */
    public $lastListPerPage = 20;

    public function __construct() {
    }

    public function listPageForEvent(int $eventId, int $page = 1, int $perPage = 20, string $scope = 'all'): array {
        $this->lastListScope = $scope;
        $this->lastListPage = $page;
        $this->lastListPerPage = $perPage;

        return [
            'items' => [
                (object) [
                    'id' => 1,
                    'event_id' => $eventId,
                    'type' => 'urgent',
                    'text' => 'Melding',
                    'visibility' => $scope === 'global' ? 'global' : 'intern',
                    'status' => 'actief',
                ],
            ],
            'total' => 42,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function create(array $payload) {
        $this->lastCreateScope = (string) ($payload['scope'] ?? 'event');
        if ((string) ($payload['text'] ?? '') === '') {
            throw new InvalidArgumentException('text is verplicht.');
        }

        return (object) [
            'id' => 2,
            'event_id' => (int) ($payload['event_id'] ?? 0),
            'type' => (string) ($payload['type'] ?? 'info'),
            'text' => (string) ($payload['text'] ?? ''),
            'visibility' => $this->lastCreateScope === 'global' ? 'global' : 'intern',
            'status' => (string) ($payload['status'] ?? 'actief'),
        ];
    }

    public function setStatus(int $messageId, int $eventId, string $status, string $changedBy = 'admin') {
        $this->lastSetStatus = $status;

        return (object) [
            'id' => $messageId,
            'event_id' => $eventId,
            'status' => $status,
        ];
    }
}

class FakeDashboardMessageRequest {
    /** @var array<string, mixed> */
    private $params;

    /** @param array<string, mixed> $params */
    public function __construct(array $params) {
        $this->params = $params;
    }

    public function get_param(string $key) {
        return $this->params[$key] ?? null;
    }

    public function get_header(string $key): string {
        if (strtolower($key) === 'x-wp-nonce') {
            return (string) ($this->params['_header_nonce'] ?? '');
        }

        return '';
    }
}
