<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Api\EventCloseoutRestController;
use BSO\Survival\Database\Repository\AuditLogRepositoryInterface;
use BSO\Survival\Database\Repository\CertificateRepositoryInterface;
use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Database\Repository\EventPublicationRepositoryInterface;
use BSO\Survival\Service\AuditLogService;
use BSO\Survival\Service\CertificateService;
use BSO\Survival\Service\EventCloseoutService;
use BSO\Survival\Service\EventPublicationService;
use BSO\Survival\Service\EventService;
use PHPUnit\Framework\TestCase;

class EventCloseoutRestControllerTest extends TestCase {
    protected function tearDown(): void {
        reset_test_current_user_caps();
        set_test_nonce_verification_result(1);
    }

    /**
     * @test
     */
    public function it_closes_out_an_event_via_rest_trigger(): void {
        $controller = $this->buildController();

        $response = $controller->closeoutEvent(new EventCloseoutFakeRestRequest([
            'event_id' => 14,
            'changed_by' => 'wedstrijdleiding',
            'certificates' => [
                ['team_id' => 5, 'file_path' => '/tmp/team-5.pdf'],
            ],
        ]));

        $this->assertTrue($response['updated']);
        $this->assertSame('closeout', $response['phase']);
        $this->assertSame('afgesloten', $response['result']['status']);
        $this->assertCount(1, $response['result']['certificates']);
    }

    /**
     * @test
     */
    public function it_publishes_an_event_via_rest_trigger(): void {
        $controller = $this->buildController();

        $response = $controller->publishEvent(new EventCloseoutFakeRestRequest([
            'event_id' => 14,
            'changed_by' => 'wedstrijdleiding',
            'publication' => [
                'headline' => 'Uitslag gepubliceerd',
                'standings' => [
                    ['rank' => 1, 'team_id' => 1, 'team_name' => 'Team 1', 'points' => 100],
                    ['rank' => 2, 'team_id' => 2, 'team_name' => 'Team 2', 'points' => 95],
                    ['rank' => 3, 'team_id' => 3, 'team_name' => 'Team 3', 'points' => 90],
                ],
            ],
        ]));

        $this->assertTrue($response['updated']);
        $this->assertSame('publication', $response['phase']);
        $this->assertSame('gepubliceerd', $response['result']['status']);
        $this->assertSame('Uitslag gepubliceerd', $response['result']['publication']['headline']);
        $this->assertCount(3, $response['result']['publication']['top_3']);
        $this->assertCount(3, $response['result']['publication']['final_standings']);
        $this->assertSame('publication_completed', $response['result']['audit_log']->action);
    }

    /**
     * @test
     */
    public function it_requires_manage_permissions_with_rest_nonce(): void {
        set_test_current_user_caps(['manage_options' => true]);
        set_test_nonce_verification_result(false);

        $controller = $this->buildController();
        $request = new EventCloseoutFakeRestRequest(['event_id' => 14], ['X-WP-Nonce' => '']);

        $this->assertFalse($controller->canManage($request));
    }

    /**
     * @test
     */
    public function it_returns_persisted_publication_snapshot(): void {
        $controller = $this->buildController(
            new EventPublicationService(new EventCloseoutRestPublicationRepository())
        );

        $response = $controller->getPublicationResult(new EventCloseoutFakeRestRequest([
            'event_id' => 14,
        ]));

        $this->assertSame(14, $response['event_id']);
        $this->assertIsArray($response['publication']);
        $this->assertSame('Persisted uitslag', $response['publication']['headline']);
        $this->assertCount(3, $response['publication']['top_3']);
    }

    /**
     * @test
     */
    public function it_returns_null_when_no_persisted_publication_is_available(): void {
        $controller = $this->buildController();

        $response = $controller->getPublicationResult(new EventCloseoutFakeRestRequest([
            'event_id' => 14,
        ]));

        $this->assertSame(14, $response['event_id']);
        $this->assertNull($response['publication']);
    }

    private function buildController(EventPublicationService $publications = null): EventCloseoutRestController {
        return new EventCloseoutRestController(
            new EventCloseoutService(
                new EventService(new EventCloseoutRestEventRepository()),
                new CertificateService(new EventCloseoutRestCertificateRepository()),
                new AuditLogService(new EventCloseoutRestAuditLogRepository())
            ),
            $publications
        );
    }
}

class EventCloseoutFakeRestRequest {
    /** @var array<string, mixed> */
    private $params;

    /** @var array<string, string> */
    private $headers;

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $headers
     */
    public function __construct(array $params, array $headers = []) {
        $this->params = $params;
        $this->headers = $headers;
    }

    public function get_param(string $key) {
        return $this->params[$key] ?? null;
    }

    public function get_header(string $key): string {
        return (string) ($this->headers[$key] ?? '');
    }
}

class EventCloseoutRestEventRepository implements EventRepositoryInterface {
    /** @var array<int, string> */
    private $statuses = [14 => 'afgesloten'];

    public function findAll(): array {
        return [(object) ['id' => 14, 'status' => $this->statuses[14]]];
    }

    public function findById(int $id) {
        return isset($this->statuses[$id]) ? (object) ['id' => $id, 'status' => $this->statuses[$id]] : null;
    }

    public function findByStatus(string $status): array {
        return [];
    }

    public function updateStatus(int $id, string $status): bool {
        if (!isset($this->statuses[$id])) {
            return false;
        }

        $this->statuses[$id] = $status;
        return true;
    }
}

class EventCloseoutRestCertificateRepository implements CertificateRepositoryInterface {
    private $rows = [];

    public function insert(array $data) {
        $id = count($this->rows) + 1;
        $row = (object) array_merge(['id' => $id], $data);
        $this->rows[$id] = $row;
        return $row;
    }
}

class EventCloseoutRestAuditLogRepository implements AuditLogRepositoryInterface {
    private $rows = [];

    public function insert(array $data) {
        $id = count($this->rows) + 1;
        $row = (object) array_merge(['id' => $id], $data);
        $this->rows[$id] = $row;
        return $row;
    }
}

class EventCloseoutRestPublicationRepository implements EventPublicationRepositoryInterface {
    public function findByEventId(int $eventId) {
        if ($eventId !== 14) {
            return null;
        }

        return (object) [
            'headline' => 'Persisted uitslag',
            'published_at' => '2026-07-08T10:00:00+00:00',
            'top_3_json' => json_encode([
                ['rank' => 1, 'team_name' => 'Team A', 'points' => 120],
                ['rank' => 2, 'team_name' => 'Team B', 'points' => 110],
                ['rank' => 3, 'team_name' => 'Team C', 'points' => 105],
            ]),
            'final_standings_json' => json_encode([
                ['rank' => 1, 'team_name' => 'Team A', 'points' => 120],
                ['rank' => 2, 'team_name' => 'Team B', 'points' => 110],
                ['rank' => 3, 'team_name' => 'Team C', 'points' => 105],
                ['rank' => 4, 'team_name' => 'Team D', 'points' => 95],
            ]),
            'changed_by' => 'wedstrijdleiding',
        ];
    }

    public function upsertByEventId(int $eventId, array $data) {
        return null;
    }
}
