<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Api\AdminScoreRestController;
use BSO\Survival\Service\AdminScoreService;
use PHPUnit\Framework\TestCase;

class AdminScoreRestControllerTest extends TestCase {
    protected function tearDown(): void {
        set_test_nonce_verification_result(1);
        reset_test_current_user_caps();
    }

    /** @test */
    public function it_recalculates_part_scores_via_rest(): void {
        $service = new FakeAdminScoreService();
        $controller = new AdminScoreRestController($service);

        $response = $controller->recalculatePart(new FakeAdminScoreRestRequest([
            'event_id' => 9,
            'part_id' => 31,
            'changed_by' => 'admin',
        ]));

        $this->assertTrue($response['success']);
        $this->assertSame(9, $response['data']['result']['event_id']);
        $this->assertSame(31, $response['data']['result']['part_id']);
        $this->assertSame(2, $response['data']['result']['team_count']);
        $this->assertSame(1, count($service->recalculateCalls));
        $this->assertSame(9, $service->recalculateCalls[0]['event_id']);
        $this->assertSame(31, $service->recalculateCalls[0]['part_id']);
    }

    /** @test */
    public function it_returns_400_for_invalid_recalculate_payload(): void {
        $service = new FakeAdminScoreService();
        $service->mode = 'invalid';
        $controller = new AdminScoreRestController($service);

        $response = $controller->recalculatePart(new FakeAdminScoreRestRequest([
            'event_id' => 0,
            'part_id' => 0,
        ]));

        $this->assertFalse($response['success']);
        $this->assertSame('invalid_recalculate_input', $response['error']['code']);
        $this->assertSame(400, $response['error']['status']);
    }

    /** @test */
    public function it_requires_score_capability_or_admin_fallback_and_valid_nonce(): void {
        set_test_current_user_caps([
            'manage_survival_scores' => false,
            'manage_options' => false,
        ]);
        $controller = new AdminScoreRestController(new FakeAdminScoreService());
        $this->assertFalse($controller->canManage(new FakeAdminScoreRestRequest([])));

        set_test_current_user_caps([
            'manage_survival_scores' => true,
            'manage_options' => false,
        ]);
        $this->assertTrue($controller->canManage(new FakeAdminScoreRestRequest([
            '_header_nonce' => 'ok',
        ])));

        set_test_current_user_caps([
            'manage_survival_scores' => false,
            'manage_options' => true,
        ]);
        $this->assertTrue($controller->canManage(new FakeAdminScoreRestRequest([
            '_header_nonce' => 'ok',
        ])));

        set_test_current_user_caps([
            'manage_survival_scores' => true,
            'manage_options' => false,
        ]);
        set_test_nonce_verification_result(false);

        $this->assertFalse($controller->canManage(new FakeAdminScoreRestRequest([
            '_header_nonce' => '',
        ])));
    }
}

class FakeAdminScoreService extends AdminScoreService {
    /** @var array<int, array<string, mixed>> */
    public $recalculateCalls = [];

    /** @var string */
    public $mode = 'ok';

    public function __construct() {
    }

    public function create(array $payload): array {
        return [];
    }

    public function update(int $scoreEntryId, array $payload): array {
        return [];
    }

    public function recalculate(array $payload): array {
        $this->recalculateCalls[] = $payload;

        if ($this->mode === 'invalid') {
            throw new \InvalidArgumentException('event_id must be a positive integer.');
        }

        return [
            'event_id' => (int) ($payload['event_id'] ?? 0),
            'part_id' => (int) ($payload['part_id'] ?? 0),
            'team_count' => 2,
            'positions' => [
                5 => 1,
                8 => 2,
            ],
        ];
    }
}

class FakeAdminScoreRestRequest {
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
