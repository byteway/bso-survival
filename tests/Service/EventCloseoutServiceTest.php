<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\AuditLogRepositoryInterface;
use BSO\Survival\Database\Repository\CertificateRepositoryInterface;
use BSO\Survival\Database\Repository\EventPublicationRepositoryInterface;
use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Service\AuditLogService;
use BSO\Survival\Service\CertificateService;
use BSO\Survival\Service\EventCloseoutService;
use BSO\Survival\Service\EventPublicationService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PublicationNotificationService;
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

    /**
     * @test
     */
    public function it_publishes_with_concrete_top_three_and_notification_summary(): void {
        $notificationCalls = [];
        add_action('bso_survival_publication_notifications_sent', function ($eventId, $summary, $publication, $changedBy) use (&$notificationCalls): void {
            $notificationCalls[] = [$eventId, $summary, $publication, $changedBy];
        }, 10, 4);

        $eventRepository = new CloseoutEventRepository();
        $publicationRepository = new CloseoutPublicationRepository();
        $service = new EventCloseoutService(
            new EventService($eventRepository),
            new CertificateService(new CloseoutCertificateRepository()),
            new AuditLogService(new CloseoutAuditLogRepository()),
            new PublicationNotificationService(),
            new EventPublicationService($publicationRepository)
        );

        $result = $service->publishEvent(5, 'wedstrijdleiding', [
            'headline' => 'Eindstand BSO Survival 2026',
            'standings' => [
                ['rank' => 1, 'team_id' => 11, 'team_name' => 'Team Rood', 'points' => 98.5],
                ['rank' => 2, 'team_id' => 22, 'team_name' => 'Team Blauw', 'points' => 96.25],
                ['rank' => 3, 'team_id' => 33, 'team_name' => 'Team Groen', 'points' => 92.75],
                ['rank' => 4, 'team_id' => 44, 'team_name' => 'Team Geel', 'points' => 89.0],
            ],
            'recipients' => ['coach@example.test', 'Coach@example.test', 'jury@example.test'],
        ]);

        $this->assertSame('gepubliceerd', $result['status']);
        $this->assertSame('Eindstand BSO Survival 2026', $result['publication']['headline']);
        $this->assertCount(4, $result['publication']['final_standings']);
        $this->assertCount(3, $result['publication']['top_3']);
        $this->assertSame('Team Rood', $result['publication']['top_3'][0]['team_name']);
        $this->assertSame('Team Groen', $result['publication']['top_3'][2]['team_name']);
        $this->assertSame('gepubliceerd', $eventRepository->statusById[5]);

        $this->assertIsArray($result['notifications']);
        $this->assertSame(2, $result['notifications']['sent_count']);
        $this->assertSame(0, $result['notifications']['failed_count']);
        $this->assertSame(['coach@example.test', 'jury@example.test'], $result['notifications']['sent_to']);
        $this->assertIsArray($result['publication_persisted']);
        $this->assertSame('Eindstand BSO Survival 2026', $result['publication_persisted']['headline']);
        $this->assertCount(4, $result['publication_persisted']['final_standings']);
        $this->assertSame(1, count($notificationCalls));
        $this->assertSame(5, $notificationCalls[0][0]);
        $this->assertSame('wedstrijdleiding', $notificationCalls[0][3]);
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

class CloseoutPublicationRepository implements EventPublicationRepositoryInterface {
    /** @var array<int, object> */
    private $rows = [];

    public function findByEventId(int $eventId) {
        return $this->rows[$eventId] ?? null;
    }

    public function upsertByEventId(int $eventId, array $data) {
        $existing = $this->rows[$eventId] ?? (object) ['id' => count($this->rows) + 1, 'event_id' => $eventId];
        $this->rows[$eventId] = (object) array_merge((array) $existing, ['event_id' => $eventId], $data);

        return $this->rows[$eventId];
    }
}
