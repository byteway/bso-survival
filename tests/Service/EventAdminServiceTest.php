<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\EventAdminRepositoryInterface;
use BSO\Survival\Database\Repository\EventPublicationRepositoryInterface;
use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Database\Repository\PartAdminRepositoryInterface;
use BSO\Survival\Service\EventAdminService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EventAdminServiceTest extends TestCase {
    /** @test */
    public function it_creates_event_with_default_status_and_meta(): void {
        $events = new InMemoryEventReadRepository();
        $eventAdmin = new InMemoryEventAdminRepository($events);
        $parts = new InMemoryPartAdminRepository();
        $publications = new InMemoryEventPublicationRepository();

        $service = new EventAdminService($events, $eventAdmin, $parts, $publications);
        $created = $service->createEvent('Survival Najaar', '2026-10-05', 24);

        $this->assertGreaterThan(0, (int) ($created->id ?? 0));
        $this->assertSame('concept', (string) ($created->status ?? ''));

        $meta = json_decode((string) ($created->meta_data ?? ''), true);
        $this->assertIsArray($meta);
        $this->assertSame(24, (int) ($meta['max_teams'] ?? 0));
    }

    /** @test */
    public function it_links_parts_to_event_and_unlinks_missing_selection(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) [
            'id' => 10,
            'name' => 'Event 10',
            'status' => 'concept',
        ]);

        $eventAdmin = new InMemoryEventAdminRepository($events);
        $parts = new InMemoryPartAdminRepository();
        $parts->seed((object) ['id' => 101, 'name' => 'Klimmuur', 'event_id' => 10]);
        $parts->seed((object) ['id' => 102, 'name' => 'Touwbrug', 'event_id' => null]);
        $parts->seed((object) ['id' => 103, 'name' => 'Balk', 'event_id' => null]);

        $publications = new InMemoryEventPublicationRepository();

        $service = new EventAdminService($events, $eventAdmin, $parts, $publications);
        $result = $service->linkPartsToEvent(10, [102, 103]);

        $this->assertSame(2, $result['linked_count']);
        $this->assertSame(1, $result['unlinked_count']);
        $this->assertNull($parts->findOne(101)->event_id ?? null);
        $this->assertSame(10, (int) ($parts->findOne(102)->event_id ?? 0));
        $this->assertSame(10, (int) ($parts->findOne(103)->event_id ?? 0));
    }

    /** @test */
    public function it_rejects_linking_parts_for_closed_event(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) [
            'id' => 11,
            'name' => 'Event 11',
            'status' => 'afgesloten',
        ]);

        $eventAdmin = new InMemoryEventAdminRepository($events);
        $parts = new InMemoryPartAdminRepository();
        $parts->seed((object) ['id' => 201, 'name' => 'Bandensprint', 'event_id' => null]);

        $publications = new InMemoryEventPublicationRepository();

        $service = new EventAdminService($events, $eventAdmin, $parts, $publications);

        $this->expectException(\RuntimeException::class);
        $service->linkPartsToEvent(11, [201]);
    }

    /** @test */
    public function it_rejects_linking_duplicate_part_names_to_same_event(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) [
            'id' => 14,
            'name' => 'Event 14',
            'status' => 'concept',
        ]);

        $eventAdmin = new InMemoryEventAdminRepository($events);
        $parts = new InMemoryPartAdminRepository();
        $parts->seed((object) ['id' => 401, 'name' => 'Kanovaren', 'event_id' => 14]);
        $parts->seed((object) ['id' => 402, 'name' => 'Kanovaren', 'event_id' => null]);

        $publications = new InMemoryEventPublicationRepository();

        $service = new EventAdminService($events, $eventAdmin, $parts, $publications);

        $this->expectException(\RuntimeException::class);
        $service->linkPartsToEvent(14, [401, 402]);
    }

    /** @test */
    public function it_marks_closed_event_deleted_and_keeps_summary_while_detaching_parts(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) [
            'id' => 12,
            'name' => 'Event 12',
            'status' => 'afgesloten',
        ]);

        $eventAdmin = new InMemoryEventAdminRepository($events);
        $parts = new InMemoryPartAdminRepository();
        $parts->seed((object) ['id' => 301, 'name' => 'Netklim', 'event_id' => 12]);
        $parts->seed((object) ['id' => 302, 'name' => 'Sprint', 'event_id' => 12]);

        $publications = new InMemoryEventPublicationRepository();
        $publications->seed(12, (object) [
            'event_id' => 12,
            'headline' => 'Uitslag Event 12',
        ]);

        $service = new EventAdminService($events, $eventAdmin, $parts, $publications);
        $result = $service->deleteEventFromAdmin(12);

        $this->assertSame('verwijderd', $result['status']);
        $this->assertSame(2, $result['detached_parts']);
        $this->assertTrue($result['summary_retained']);
        $this->assertSame('verwijderd', (string) ($events->findById(12)->status ?? ''));
        $this->assertNull($parts->findOne(301)->event_id ?? null);
        $this->assertNull($parts->findOne(302)->event_id ?? null);
    }

    /** @test */
    public function it_rejects_deleting_closed_event_without_summary(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) [
            'id' => 13,
            'name' => 'Event 13',
            'status' => 'afgesloten',
        ]);

        $eventAdmin = new InMemoryEventAdminRepository($events);
        $parts = new InMemoryPartAdminRepository();
        $publications = new InMemoryEventPublicationRepository();

        $service = new EventAdminService($events, $eventAdmin, $parts, $publications);

        $this->expectException(\RuntimeException::class);
        $service->deleteEventFromAdmin(13);
    }

    /** @test */
    public function it_rejects_empty_event_name_on_create(): void {
        $service = new EventAdminService(
            new InMemoryEventReadRepository(),
            new InMemoryEventAdminRepository(new InMemoryEventReadRepository()),
            new InMemoryPartAdminRepository(),
            new InMemoryEventPublicationRepository()
        );

        $this->expectException(InvalidArgumentException::class);
        $service->createEvent('', '2026-09-01', 22);
    }
}

class InMemoryEventReadRepository implements EventRepositoryInterface {
    /** @var array<int, object> */
    private $rows = [];

    public function findAll(): array {
        return array_values($this->rows);
    }

    public function findById(int $id) {
        return $this->rows[$id] ?? null;
    }

    public function findByStatus(string $status): array {
        return array_values(array_filter($this->rows, static function ($row) use ($status): bool {
            return (string) ($row->status ?? '') === $status;
        }));
    }

    public function updateStatus(int $id, string $status): bool {
        if (!isset($this->rows[$id])) {
            return false;
        }

        $this->rows[$id]->status = $status;
        return true;
    }

    public function seed(object $row): void {
        $id = (int) ($row->id ?? 0);
        if ($id <= 0) {
            $id = count($this->rows) + 1;
            $row->id = $id;
        }

        $this->rows[$id] = $row;
    }
}

class InMemoryEventAdminRepository implements EventAdminRepositoryInterface {
    /** @var InMemoryEventReadRepository */
    private $events;

    public function __construct(InMemoryEventReadRepository $events) {
        $this->events = $events;
    }

    public function create(array $data) {
        $row = (object) array_merge(['id' => rand(1000, 9999)], $data);
        $this->events->seed($row);
        return $row;
    }

    public function markDeleted(int $eventId): bool {
        return $this->events->updateStatus($eventId, 'verwijderd');
    }
}

class InMemoryPartAdminRepository implements PartAdminRepositoryInterface {
    /** @var array<int, object> */
    private $rows = [];

    public function findAll(): array {
        return array_values($this->rows);
    }

    public function findByIds(array $partIds): array {
        $result = [];
        foreach ($partIds as $partId) {
            $id = (int) $partId;
            if (isset($this->rows[$id])) {
                $result[] = $this->rows[$id];
            }
        }

        return $result;
    }

    public function findByEventId(int $eventId): array {
        return array_values(array_filter($this->rows, static function ($row) use ($eventId): bool {
            return (int) ($row->event_id ?? 0) === $eventId;
        }));
    }

    public function assignToEvent(int $partId, ?int $eventId): bool {
        if (!isset($this->rows[$partId])) {
            return false;
        }

        $this->rows[$partId]->event_id = $eventId;
        return true;
    }

    public function seed(object $row): void {
        $id = (int) ($row->id ?? 0);
        if ($id <= 0) {
            $id = count($this->rows) + 1;
            $row->id = $id;
        }

        $this->rows[$id] = $row;
    }

    public function findOne(int $partId) {
        return $this->rows[$partId] ?? null;
    }
}

class InMemoryEventPublicationRepository implements EventPublicationRepositoryInterface {
    /** @var array<int, object> */
    private $rows = [];

    public function findByEventId(int $eventId) {
        return $this->rows[$eventId] ?? null;
    }

    public function upsertByEventId(int $eventId, array $data) {
        $row = (object) array_merge(['event_id' => $eventId], $data);
        $this->rows[$eventId] = $row;
        return $row;
    }

    public function seed(int $eventId, object $row): void {
        $this->rows[$eventId] = $row;
    }
}
