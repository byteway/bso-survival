<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Api\AdminScoreV2RestController;
use BSO\Survival\Service\AdminScoreService;
use PHPUnit\Framework\TestCase;

class AdminScoreV2RestControllerTest extends TestCase {
    protected function tearDown(): void {
        set_test_nonce_verification_result(1);
        reset_test_current_user_caps();
    }

    /** @test */
    public function it_creates_score_entry_via_v2_with_standardized_meta_block(): void {
        $service = new FakeAdminScoreV2Service();
        $controller = new AdminScoreV2RestController($service);

        $response = $controller->createEntry(new FakeAdminScoreV2RestRequest([
            'event_id' => 9,
            'assignment_id' => 101,
            'raw_value' => 55,
            'changed_by' => 'admin',
            'entered_by_role' => 'jury',
            'meta' => [
                'source' => 'admin',
                'labels' => ['operations'],
                'trace_id' => 'trace-9',
            ],
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame('admin', $service->lastCreatePayload['meta']['source']);
        $this->assertSame(['operations'], $service->lastCreatePayload['meta']['labels']);
        $this->assertSame('trace-9', $service->lastCreatePayload['meta']['trace_id']);
    }

    /** @test */
    public function it_returns_invalid_meta_block_for_unknown_meta_fields(): void {
        $service = new FakeAdminScoreV2Service();
        $controller = new AdminScoreV2RestController($service);

        $response = $controller->createEntry(new FakeAdminScoreV2RestRequest([
            'event_id' => 9,
            'assignment_id' => 101,
            'raw_value' => 55,
            'meta' => [
                'unsupported' => 'x',
            ],
        ]));

        $this->assertFalse($response['success']);
        $this->assertSame('invalid_meta_block', $response['error']['code']);
        $this->assertSame(400, $response['error']['status']);
    }

    /** @test */
    public function it_updates_score_entry_via_v2_with_meta_block(): void {
        $service = new FakeAdminScoreV2Service();
        $controller = new AdminScoreV2RestController($service);

        $response = $controller->updateEntry(new FakeAdminScoreV2RestRequest([
            'score_entry_id' => 77,
            'event_id' => 9,
            'raw_value' => 44,
            'meta' => [
                'source' => 'admin_score_edit',
                'labels' => ['audit'],
                'trace_id' => 'trace-77',
            ],
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame('admin_score_edit', $service->lastUpdatePayload['meta']['source']);
        $this->assertSame(['audit'], $service->lastUpdatePayload['meta']['labels']);
        $this->assertSame('trace-77', $service->lastUpdatePayload['meta']['trace_id']);
    }

    /** @test */
    public function it_requires_score_capability_or_admin_fallback_and_valid_nonce(): void {
        set_test_current_user_caps([
            'manage_survival_scores' => false,
            'manage_options' => false,
        ]);
        $controller = new AdminScoreV2RestController(new FakeAdminScoreV2Service());
        $this->assertFalse($controller->canManage(new FakeAdminScoreV2RestRequest([])));

        set_test_current_user_caps([
            'manage_survival_scores' => true,
            'manage_options' => false,
        ]);
        $this->assertTrue($controller->canManage(new FakeAdminScoreV2RestRequest([
            '_header_nonce' => 'ok',
        ])));

        set_test_current_user_caps([
            'manage_survival_scores' => false,
            'manage_options' => true,
        ]);
        $this->assertTrue($controller->canManage(new FakeAdminScoreV2RestRequest([
            '_header_nonce' => 'ok',
        ])));

        set_test_current_user_caps([
            'manage_survival_scores' => true,
            'manage_options' => false,
        ]);
        set_test_nonce_verification_result(false);

        $this->assertFalse($controller->canManage(new FakeAdminScoreV2RestRequest([
            '_header_nonce' => '',
        ])));
    }
}

class FakeAdminScoreV2Service extends AdminScoreService {
    /** @var array<string, mixed> */
    public $lastCreatePayload = [];

    /** @var array<string, mixed> */
    public $lastUpdatePayload = [];

    public function __construct() {
    }

    public function create(array $payload): array {
        $this->lastCreatePayload = $payload;

        return [
            'score_entry_id' => 901,
            'assignment_id' => (int) ($payload['assignment_id'] ?? 0),
            'event_id' => (int) ($payload['event_id'] ?? 0),
            'part_id' => 31,
            'normalized_points' => 55.0,
            'positions' => [11 => 1],
        ];
    }

    public function update(int $scoreEntryId, array $payload): array {
        $this->lastUpdatePayload = $payload;

        return [
            'score_entry_id' => $scoreEntryId,
            'assignment_id' => 101,
            'event_id' => (int) ($payload['event_id'] ?? 0),
            'part_id' => 31,
            'normalized_points' => 44.0,
            'positions' => [11 => 1],
        ];
    }
}

class FakeAdminScoreV2RestRequest {
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
