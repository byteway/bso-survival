<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Database\Repository\RegistrationWindowRepositoryInterface;
use BSO\Survival\Database\Repository\TeamMemberRepositoryInterface;
use BSO\Survival\Database\Repository\TeamRepositoryInterface;
use BSO\Survival\Service\EmailOutboxService;
use BSO\Survival\Service\EmailTemplateService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\RegistrationConfirmationService;
use BSO\Survival\Service\TeamRegistrationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TeamRegistrationServiceTest extends TestCase {
    /** @test */
    public function it_registers_team_members_and_queues_confirmation(): void {
        $events = new RegistrationEventRepository();
        $teams = new RegistrationTeamRepository();
        $members = new RegistrationTeamMemberRepository();
        $windows = new OpenRegistrationWindowRepository();
        $confirmations = new FakeRegistrationConfirmationService();
        $tx = new FakeTransactionWpdb();

        $service = new TeamRegistrationService(
            new EventService($events),
            $teams,
            $members,
            $windows,
            $confirmations,
            $tx
        );

        $result = $service->register([
            'event_id' => 5,
            'team_name' => 'Team Kompas',
            'contact_name' => 'Ouder Voorbeeld',
            'contact_email' => 'ouder@example.test',
            'contact_phone' => '0612345678',
            'team_members' => ['Kind 1', 'Kind 2', 'Kind 3'],
            'idempotency_key' => 'abc-123',
        ]);

        $this->assertSame('registered', $result['status']);
        $this->assertSame(1, $result['counts']['registered_teams']);
        $this->assertSame(3, $result['counts']['max_teams']);
        $this->assertSame(1, count($members->rows));
        $this->assertTrue($result['confirmation_queued']);
        $this->assertSame(1, count($confirmations->calls));

        $this->assertSame(['START TRANSACTION', 'COMMIT'], $tx->queries);
    }

    /** @test */
    public function it_blocks_registration_when_window_is_closed(): void {
        $service = new TeamRegistrationService(
            new EventService(new RegistrationEventRepository()),
            new RegistrationTeamRepository(),
            new RegistrationTeamMemberRepository(),
            new ClosedRegistrationWindowRepository(),
            null,
            new FakeTransactionWpdb()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Inschrijving is op dit moment gesloten.');

        $service->register([
            'event_id' => 5,
            'team_name' => 'Team Kompas',
            'contact_name' => 'Ouder',
            'contact_email' => 'ouder@example.test',
            'contact_phone' => '0612345678',
            'team_members' => ['Kind 1'],
        ]);
    }

    /** @test */
    public function it_blocks_registration_when_capacity_is_full(): void {
        $teams = new RegistrationTeamRepository();
        $teams->seedExisting(5, 'Team A');
        $teams->seedExisting(5, 'Team B');
        $teams->seedExisting(5, 'Team C');

        $service = new TeamRegistrationService(
            new EventService(new RegistrationEventRepository()),
            $teams,
            new RegistrationTeamMemberRepository(),
            new OpenRegistrationWindowRepository(),
            null,
            new FakeTransactionWpdb()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Inschrijving is vol voor dit event.');

        $service->register([
            'event_id' => 5,
            'team_name' => 'Team D',
            'contact_name' => 'Ouder',
            'contact_email' => 'ouder@example.test',
            'contact_phone' => '0612345678',
            'team_members' => ['Kind 1'],
        ]);
    }

    /** @test */
    public function it_returns_already_registered_for_duplicate_team_name(): void {
        $teams = new RegistrationTeamRepository();
        $teams->seedExisting(5, 'Team Kompas');

        $service = new TeamRegistrationService(
            new EventService(new RegistrationEventRepository()),
            $teams,
            new RegistrationTeamMemberRepository(),
            new OpenRegistrationWindowRepository(),
            null,
            new FakeTransactionWpdb()
        );

        $result = $service->register([
            'event_id' => 5,
            'team_name' => 'Team Kompas',
            'contact_name' => 'Ouder',
            'contact_email' => 'ouder@example.test',
            'contact_phone' => '0612345678',
            'team_members' => ['Kind 1'],
        ]);

        $this->assertSame('already_registered', $result['status']);
        $this->assertSame(1, $result['counts']['registered_teams']);
    }
}

class RegistrationEventRepository implements EventRepositoryInterface {
    public function findAll(): array { return []; }

    public function findById(int $id) {
        if ($id !== 5) {
            return null;
        }

        return (object) [
            'id' => 5,
            'name' => 'Event 5',
            'event_date' => '2026-07-08',
            'meta_data' => json_encode(['max_teams' => 3]),
        ];
    }

    public function findByStatus(string $status): array { return []; }

    public function updateStatus(int $id, string $status): bool { return true; }
}

class RegistrationTeamRepository implements TeamRepositoryInterface {
    /** @var array<int, object> */
    private $rows = [];

    /** @var int */
    private $nextId = 1;

    public function findById(int $id) {
        return $this->rows[$id] ?? null;
    }

    public function findByEventId(int $eventId): array {
        return array_values(array_filter($this->rows, static function ($row) use ($eventId): bool {
            return (int) ($row->event_id ?? 0) === $eventId;
        }));
    }

    public function countByEventId(int $eventId): int {
        return count($this->findByEventId($eventId));
    }

    public function findByEventIdAndName(int $eventId, string $name) {
        foreach ($this->rows as $row) {
            if ((int) ($row->event_id ?? 0) === $eventId && (string) ($row->name ?? '') === $name) {
                return $row;
            }
        }

        return null;
    }

    public function create(array $data) {
        $id = $this->nextId++;
        $row = (object) array_merge(['id' => $id], $data);
        $this->rows[$id] = $row;

        return $row;
    }

    public function seedExisting(int $eventId, string $name): void {
        $this->create([
            'event_id' => $eventId,
            'name' => $name,
        ]);
    }
}

class RegistrationTeamMemberRepository implements TeamMemberRepositoryInterface {
    /** @var array<int, array<int, array<string, mixed>>> */
    public $rows = [];

    public function create(array $data) {
        return (object) array_merge(['id' => 1], $data);
    }

    public function createBatch(array $rows): int {
        $this->rows[] = $rows;
        return count($rows);
    }
}

class OpenRegistrationWindowRepository implements RegistrationWindowRepositoryInterface {
    public function findOpenForEventAt(int $eventId, string $momentUtc) {
        return (object) ['id' => 1, 'event_id' => $eventId, 'status' => 'open'];
    }
}

class ClosedRegistrationWindowRepository implements RegistrationWindowRepositoryInterface {
    public function findOpenForEventAt(int $eventId, string $momentUtc) {
        return null;
    }
}

class FakeRegistrationConfirmationService extends RegistrationConfirmationService {
    /** @var array<int, array<string, mixed>> */
    public $calls = [];

    public function __construct() {
    }

    public function enqueueForRegistration(array $registration): bool {
        $this->calls[] = $registration;
        return true;
    }
}

class FakeTransactionWpdb {
    /** @var array<int, string> */
    public $queries = [];

    public function query(string $sql): void {
        $this->queries[] = $sql;
    }
}
