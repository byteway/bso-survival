<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Api\FrontendScoreRestController;
use BSO\Survival\Service\FrontendScoreSubmissionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FrontendScoreRestControllerTest extends TestCase {
    protected function tearDown(): void {
        set_test_nonce_verification_result(1);
        reset_test_current_user_caps();
    }

    /** @test */
    public function it_submits_score_via_rest(): void {
        $service = new FakeFrontendScoreSubmissionFacadeService();
        $controller = new FrontendScoreRestController($service);

        $response = $controller->submitScore(new FakeFrontendScoreRestRequest([
            'event_id' => 7,
            'assignment_id' => 100,
            'raw_value' => 48.5,
            'entered_by_role' => 'jury',
        ]));

        $this->assertTrue($response['created']);
        $this->assertSame(1, $response['result']['score_entry_id']);
        $this->assertSame(1, count($service->calls));
        $this->assertSame(100, $service->calls[0]['assignment_id']);
    }

    /** @test */
    public function it_returns_400_for_invalid_payload(): void {
        $service = new FakeFrontendScoreSubmissionFacadeService();
        $service->mode = 'invalid';
        $controller = new FrontendScoreRestController($service);

        $response = $controller->submitScore(new FakeFrontendScoreRestRequest([
            'event_id' => 7,
            'assignment_id' => 0,
            'raw_value' => 48.5,
        ]));

        $this->assertSame('invalid_score_input', $response['error']['code']);
        $this->assertSame(400, $response['error']['status']);
    }

    /** @test */
    public function it_returns_409_when_event_is_read_only(): void {
        $service = new FakeFrontendScoreSubmissionFacadeService();
        $service->mode = 'blocked';
        $controller = new FrontendScoreRestController($service);

        $response = $controller->submitScore(new FakeFrontendScoreRestRequest([
            'event_id' => 7,
            'assignment_id' => 100,
            'raw_value' => 48.5,
        ]));

        $this->assertSame('score_submit_blocked', $response['error']['code']);
        $this->assertSame(409, $response['error']['status']);
    }

    /** @test */
    public function it_requires_valid_nonce_for_submit_permission(): void {
        set_test_nonce_verification_result(false);
        set_test_current_user_caps(['read' => true]);

        $controller = new FrontendScoreRestController(new FakeFrontendScoreSubmissionFacadeService());
        $request = new FakeFrontendScoreRestRequest(['score_nonce' => '']);

        $this->assertFalse($controller->canSubmit($request));
    }
}

class FakeFrontendScoreSubmissionFacadeService extends FrontendScoreSubmissionService {
    /** @var array<int, array<string, mixed>> */
    public $calls = [];

    /** @var string */
    public $mode = 'ok';

    public function __construct() {
    }

    public function submit(array $payload): array {
        $this->calls[] = $payload;

        if ($this->mode === 'invalid') {
            throw new InvalidArgumentException('assignment_id must be a positive integer.');
        }

        if ($this->mode === 'blocked') {
            throw new RuntimeException('Score-invoer is geblokkeerd.');
        }

        return [
            'score_entry_id' => 1,
            'assignment_id' => (int) ($payload['assignment_id'] ?? 0),
            'part_id' => 30,
            'raw_value' => 48.5,
            'normalized_points' => 48.5,
            'status' => 'concept',
            'status_flags' => [
                'is_read_only' => false,
                'is_published' => false,
            ],
            'counts' => [
                'parts' => 4,
                'teams' => 8,
            ],
        ];
    }
}

class FakeFrontendScoreRestRequest {
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
        return '';
    }
}
