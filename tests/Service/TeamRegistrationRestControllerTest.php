<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Api\TeamRegistrationRestController;
use BSO\Survival\Service\TeamRegistrationService;
use PHPUnit\Framework\TestCase;

class TeamRegistrationRestControllerTest extends TestCase {
    protected function tearDown(): void {
        set_test_nonce_verification_result(1);
    }

    /** @test */
    public function it_creates_registration_via_rest(): void {
        $service = new FakeTeamRegistrationService();
        $controller = new TeamRegistrationRestController($service);

        $response = $controller->createRegistration(new FakeTeamRegistrationRequest([
            'event_id' => 7,
            'team_name' => 'Team Kompas',
            'contact_name' => 'Ouder',
            'contact_email' => 'ouder@example.test',
            'contact_phone' => '06123',
            'team_members' => ['Kind 1', 'Kind 2'],
            'idempotency_key' => 'abc',
        ]));

        $this->assertTrue($response['created']);
        $this->assertSame('registered', $response['result']['status']);
        $this->assertSame(1, count($service->calls));
        $this->assertSame(7, $service->calls[0]['event_id']);
    }

    /** @test */
    public function it_requires_valid_registration_nonce(): void {
        set_test_nonce_verification_result(false);

        $controller = new TeamRegistrationRestController(new FakeTeamRegistrationService());
        $request = new FakeTeamRegistrationRequest(['registration_nonce' => '']);

        $this->assertFalse($controller->canSubmit($request));
    }
}

class FakeTeamRegistrationService extends TeamRegistrationService {
    /** @var array<int, array<string, mixed>> */
    public $calls = [];

    public function __construct() {
    }

    public function register(array $payload): array {
        $this->calls[] = $payload;

        return [
            'registration_id' => 12,
            'team_id' => 12,
            'status' => 'registered',
            'counts' => [
                'registered_teams' => 1,
                'max_teams' => 10,
            ],
        ];
    }
}

class FakeTeamRegistrationRequest {
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
