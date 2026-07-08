<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Api\DashboardMessageV2RestController;
use BSO\Survival\Service\DashboardMessageService;
use PHPUnit\Framework\TestCase;

class DashboardMessageV2RestControllerTest extends TestCase {
    protected function tearDown(): void {
        set_test_nonce_verification_result(1);
        reset_test_current_user_caps();
    }

    /** @test */
    public function it_lists_messages_with_advanced_filters_via_v2_endpoint(): void {
        $service = new V2FakeDashboardMessageService();
        $controller = new DashboardMessageV2RestController($service);

        $response = $controller->listMessages(new V2FakeDashboardMessageRequest([
            'event_id' => 7,
            'scope' => 'global',
            'status' => 'actief',
            'type' => 'warning',
            'visible_at' => '2026-07-08T10:00',
            'search' => 'briefing',
            'page' => 2,
            'per_page' => 10,
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame(2, $response['data']['pagination']['page']);
        $this->assertSame(10, $response['data']['pagination']['per_page']);
        $this->assertSame(11, $response['data']['pagination']['total']);
        $this->assertSame('global', $service->lastFilters['scope']);
        $this->assertSame('actief', $service->lastFilters['status']);
        $this->assertSame('warning', $service->lastFilters['type']);
        $this->assertSame('briefing', $service->lastFilters['search']);
        $this->assertSame('2026-07-08T10:00', $service->lastFilters['visible_at']);
    }

    /** @test */
    public function it_returns_invalid_filter_when_scope_is_invalid(): void {
        $service = new V2FakeDashboardMessageService();
        $controller = new DashboardMessageV2RestController($service);

        $response = $controller->listMessages(new V2FakeDashboardMessageRequest([
            'event_id' => 7,
            'scope' => 'unsupported',
        ]));

        $this->assertFalse($response['success']);
        $this->assertSame('invalid_filter', $response['error']['code']);
        $this->assertSame(400, $response['error']['status']);
    }

    /** @test */
    public function it_returns_invalid_pagination_when_per_page_is_invalid(): void {
        $service = new V2FakeDashboardMessageService();
        $controller = new DashboardMessageV2RestController($service);

        $response = $controller->listMessages(new V2FakeDashboardMessageRequest([
            'event_id' => 7,
            'per_page' => 101,
        ]));

        $this->assertFalse($response['success']);
        $this->assertSame('invalid_pagination', $response['error']['code']);
        $this->assertSame(400, $response['error']['status']);
    }

    /** @test */
    public function it_bulk_updates_message_status_via_v2_endpoint(): void {
        $service = new V2FakeDashboardMessageService();
        $controller = new DashboardMessageV2RestController($service);

        $response = $controller->bulkUpdateStatus(new V2FakeDashboardMessageRequest([
            'event_id' => 7,
            'message_ids' => [11, 12, 13],
            'status' => 'inactief',
            'changed_by' => 'planner',
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame(3, $response['data']['result']['updated_count']);
        $this->assertSame([11, 12, 13], $response['data']['result']['updated_ids']);
        $this->assertSame([11, 12, 13], $service->lastBulkMessageIds);
        $this->assertSame('inactief', $service->lastBulkStatus);
    }

    /** @test */
    public function it_returns_invalid_bulk_payload_when_message_ids_are_missing(): void {
        $service = new V2FakeDashboardMessageService();
        $controller = new DashboardMessageV2RestController($service);

        $response = $controller->bulkUpdateStatus(new V2FakeDashboardMessageRequest([
            'event_id' => 7,
            'message_ids' => [],
            'status' => 'inactief',
        ]));

        $this->assertFalse($response['success']);
        $this->assertSame('invalid_bulk_payload', $response['error']['code']);
        $this->assertSame(400, $response['error']['status']);
    }

    /** @test */
    public function it_returns_conflict_when_bulk_update_contains_foreign_message_ids(): void {
        $service = new V2FakeDashboardMessageService();
        $service->bulkMode = 'conflict';
        $controller = new DashboardMessageV2RestController($service);

        $response = $controller->bulkUpdateStatus(new V2FakeDashboardMessageRequest([
            'event_id' => 7,
            'message_ids' => [11, 99],
            'status' => 'inactief',
        ]));

        $this->assertFalse($response['success']);
        $this->assertSame('bulk_update_conflict', $response['error']['code']);
        $this->assertSame(409, $response['error']['status']);
    }

    /** @test */
    public function it_bulk_deletes_messages_via_v2_endpoint_when_confirmed(): void {
        $service = new V2FakeDashboardMessageService();
        $controller = new DashboardMessageV2RestController($service);

        $response = $controller->bulkDeleteMessages(new V2FakeDashboardMessageRequest([
            'event_id' => 7,
            'message_ids' => [11, 12],
            'confirm' => true,
            'changed_by' => 'planner',
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame(2, $response['data']['result']['deleted_count']);
        $this->assertSame([11, 12], $response['data']['result']['deleted_ids']);
        $this->assertSame([11, 12], $service->lastBulkDeleteMessageIds);
    }

    /** @test */
    public function it_rejects_bulk_delete_without_confirmation_flag(): void {
        $service = new V2FakeDashboardMessageService();
        $controller = new DashboardMessageV2RestController($service);

        $response = $controller->bulkDeleteMessages(new V2FakeDashboardMessageRequest([
            'event_id' => 7,
            'message_ids' => [11],
            'confirm' => false,
        ]));

        $this->assertFalse($response['success']);
        $this->assertSame('invalid_bulk_payload', $response['error']['code']);
        $this->assertSame(400, $response['error']['status']);
    }

    /** @test */
    public function it_returns_conflict_when_bulk_delete_contains_foreign_message_ids(): void {
        $service = new V2FakeDashboardMessageService();
        $service->bulkDeleteMode = 'conflict';
        $controller = new DashboardMessageV2RestController($service);

        $response = $controller->bulkDeleteMessages(new V2FakeDashboardMessageRequest([
            'event_id' => 7,
            'message_ids' => [11, 99],
            'confirm' => true,
        ]));

        $this->assertFalse($response['success']);
        $this->assertSame('bulk_delete_conflict', $response['error']['code']);
        $this->assertSame(409, $response['error']['status']);
    }

    /** @test */
    public function it_requires_message_capability_or_admin_fallback_and_valid_nonce(): void {
        set_test_current_user_caps([
            'manage_survival_messages' => false,
            'manage_options' => false,
        ]);
        $controller = new DashboardMessageV2RestController(new V2FakeDashboardMessageService());
        $this->assertFalse($controller->canManage(new V2FakeDashboardMessageRequest([])));

        set_test_current_user_caps([
            'manage_survival_messages' => true,
            'manage_options' => false,
        ]);
        $this->assertTrue($controller->canManage(new V2FakeDashboardMessageRequest([
            '_header_nonce' => 'ok',
        ])));

        set_test_current_user_caps([
            'manage_survival_messages' => false,
            'manage_options' => true,
        ]);
        $this->assertTrue($controller->canManage(new V2FakeDashboardMessageRequest([
            '_header_nonce' => 'ok',
        ])));

        set_test_current_user_caps([
            'manage_survival_messages' => true,
            'manage_options' => false,
        ]);
        set_test_nonce_verification_result(false);
        $this->assertFalse($controller->canManage(new V2FakeDashboardMessageRequest([
            '_header_nonce' => '',
        ])));
    }
}

class V2FakeDashboardMessageService extends DashboardMessageService {
    /** @var array<string, mixed> */
    public $lastFilters = [];

    /** @var array<int, int> */
    public $lastBulkMessageIds = [];

    /** @var string */
    public $lastBulkStatus = '';

    /** @var string */
    public $bulkMode = 'ok';

    /** @var array<int, int> */
    public $lastBulkDeleteMessageIds = [];

    /** @var string */
    public $bulkDeleteMode = 'ok';

    public function __construct() {
    }

    public function listAdvancedPageForEvent(int $eventId, array $filters = [], int $page = 1, int $perPage = 20): array {
        if (($filters['scope'] ?? 'all') === 'unsupported') {
            throw new \InvalidArgumentException('scope must be all, event or global.');
        }

        if ($perPage > 100) {
            throw new \InvalidArgumentException('per_page must be between 1 and 100.');
        }

        $this->lastFilters = $filters;

        return [
            'items' => [
                (object) [
                    'id' => 1,
                    'event_id' => $eventId,
                    'type' => 'warning',
                    'text' => 'Briefing over 10 minuten',
                    'visibility' => 'global',
                    'status' => 'actief',
                ],
            ],
            'total' => 11,
            'page' => $page,
            'per_page' => $perPage,
            'filters' => $filters,
        ];
    }

    public function bulkSetStatusForEvent(int $eventId, array $messageIds, string $status, string $changedBy = 'admin'): array {
        if ($messageIds === []) {
            throw new \InvalidArgumentException('message_ids must contain at least one positive integer.');
        }

        if ($this->bulkMode === 'conflict') {
            throw new \RuntimeException('Niet alle message_ids horen bij dit event_id: 99');
        }

        $this->lastBulkMessageIds = array_values(array_map('intval', $messageIds));
        $this->lastBulkStatus = $status;

        return [
            'event_id' => $eventId,
            'status' => $status,
            'updated_count' => count($this->lastBulkMessageIds),
            'updated_ids' => $this->lastBulkMessageIds,
        ];
    }

    public function bulkDeleteForEvent(int $eventId, array $messageIds, bool $confirm, string $changedBy = 'admin'): array {
        if (!$confirm) {
            throw new \InvalidArgumentException('confirm must be true for bulk delete.');
        }

        if ($messageIds === []) {
            throw new \InvalidArgumentException('message_ids must contain at least one positive integer.');
        }

        if ($this->bulkDeleteMode === 'conflict') {
            throw new \RuntimeException('Niet alle message_ids horen bij dit event_id: 99');
        }

        $this->lastBulkDeleteMessageIds = array_values(array_map('intval', $messageIds));

        return [
            'event_id' => $eventId,
            'deleted_count' => count($this->lastBulkDeleteMessageIds),
            'deleted_ids' => $this->lastBulkDeleteMessageIds,
        ];
    }
}

class V2FakeDashboardMessageRequest {
    /** @var array<string, mixed> */
    private $params;

    /**
     * @param array<string, mixed> $params
     */
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
