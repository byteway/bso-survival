<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\AuditLogRepositoryInterface;
use BSO\Survival\Database\Repository\CertificateRepositoryInterface;
use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Service\AuditLogService;
use BSO\Survival\Service\CertificateService;
use BSO\Survival\Service\EventCloseoutService;
use BSO\Survival\Service\EventService;
use PHPUnit\Framework\TestCase;

class EventCloseoutServiceTest extends TestCase {
    protected function setUp(): void {
        global $wp_actions;
        $wp_actions = [];
    }

    protected function tearDown(): void {
        global $wp_actions;
        $wp_actions = [];
    }

    /**
     * @test
     */
    public function it_closes_an_event_and_integrates_certificates_and_audit_logging(): void {
        $beforeCalls = [];
        $afterCalls = [];

        add_action('bso_survival_before_event_closeout', function ($eventId, $changedBy, $definitions, $event) use (&$beforeCalls): void {
            $beforeCalls[] = [$eventId, $changedBy, $definitions, $event];
        }, 10, 4);

        add_action('bso_survival_event_closed_out', function ($eventId, $result, $changedBy) use (&$afterCalls): void {
            $afterCalls[] = [$eventId, $result, $changedBy];
        }, 10, 3);

        $eventRepository = new CloseoutEventRepository();
        $service = new EventCloseoutService(
            new EventService($eventRepository),
            new CertificateService(new CloseoutCertificateRepository()),
            new AuditLogService(new CloseoutAuditLogRepository())
        );

        $result = $service->closeEvent(5, 'wedstrijdleiding', [
            [
                'team_id' => 91,
                'file_path' => '/tmp/team-91.pdf',
                'meta' => ['position' => 1],
            ],
            [
                'team_id' => 92,
                'file_path' => '/tmp/team-92.pdf',
                'meta' => ['position' => 2],
            ],
        ]);

        $this->assertSame(1, count($beforeCalls));
        $this->assertSame(1, count($afterCalls));
        $this->assertSame(5, $result['event_id']);
        $this->assertSame('afgesloten', $result['status']);
        $this->assertCount(2, $result['certificates']);
        $this->assertSame('afgesloten', $eventRepository->statusById[5]);
        $this->assertSame('closeout_completed', $result['audit_log']->action);
        $this->assertSame('{"status":"afgesloten","certificates":2}', $result['audit_log']->new_value);
        $this->assertSame('wedstrijdleiding', $afterCalls[0][2]);
    }
}

class CloseoutEventRepository implements EventRepositoryInterface {
    /** @var array<int, string> */
    public $statusById = [5 => 'actief'];

    /** @return array<int, object> */
    public function findAll(): array {
        return [(object) ['id' => 5, 'status' => $this->statusById[5]]];
    }

    /** @return object|null */
    public function findById(int $id) {
        return isset($this->statusById[$id]) ? (object) ['id' => $id, 'status' => $this->statusById[$id]] : null;
    }

    /** @return array<int, object> */
    public function findByStatus(string $status): array {
        return array_values(array_filter($this->findAll(), static function ($event) use ($status): bool {
            return $event->status === $status;
        }));
    }

    public function updateStatus(int $id, string $status): bool {
        if (!isset($this->statusById[$id])) {
            return false;
        }

        $this->statusById[$id] = $status;

        return true;
    }
}

class CloseoutCertificateRepository implements CertificateRepositoryInterface {
    /** @var array<int, object> */
    private $rows = [];

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data) {
        $id = count($this->rows) + 1;
        $row = (object) array_merge(['id' => $id], $data);
        $this->rows[$id] = $row;

        return $row;
    }
}

class CloseoutAuditLogRepository implements AuditLogRepositoryInterface {
    /** @var array<int, object> */
    private $rows = [];

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data) {
        $id = count($this->rows) + 1;
        $row = (object) array_merge(['id' => $id], $data);
        $this->rows[$id] = $row;

        return $row;
    }
}
