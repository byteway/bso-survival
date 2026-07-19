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
    public function it_rejects_linking_parts_for_gesloten_alias_status(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) [
            'id' => 16,
            'name' => 'Event 16',
            'status' => 'gesloten',
        ]);

        $eventAdmin = new InMemoryEventAdminRepository($events);
        $parts = new InMemoryPartAdminRepository();
        $parts->seed((object) ['id' => 601, 'name' => 'Bandensprint', 'event_id' => null]);

        $publications = new InMemoryEventPublicationRepository();

        $service = new EventAdminService($events, $eventAdmin, $parts, $publications);

        $this->expectException(\RuntimeException::class);
        $service->linkPartsToEvent(16, [601]);
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
    public function it_allows_idempotent_delete_for_already_deleted_event(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) [
            'id' => 16,
            'name' => 'Event 16',
            'status' => 'verwijderd',
        ]);

        $eventAdmin = new InMemoryEventAdminRepository($events);
        $parts = new InMemoryPartAdminRepository();
        $parts->seed((object) ['id' => 601, 'name' => 'Touwhangen', 'event_id' => 16]);

        $service = new EventAdminService($events, $eventAdmin, $parts, new InMemoryEventPublicationRepository());
        $result = $service->deleteEventFromAdmin(16);

        $this->assertSame('verwijderd', $result['status']);
        $this->assertTrue((bool) ($result['already_deleted'] ?? false));
        $this->assertSame(1, (int) ($result['detached_parts'] ?? 0));
        $this->assertNull($parts->findOne(601)->event_id ?? null);
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

    /** @test */
    public function it_updates_event_metadata_for_admin_edit(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) [
            'id' => 15,
            'name' => 'Oud event',
            'event_date' => '2026-09-01',
            'status' => 'concept',
            'meta_data' => '{"max_teams":22}',
        ]);

        $service = new EventAdminService(
            $events,
            new InMemoryEventAdminRepository($events),
            new InMemoryPartAdminRepository(),
            new InMemoryEventPublicationRepository()
        );

        $updated = $service->updateEvent(15, 'Nieuw event', '2026-09-15', 18);

        $this->assertSame('Nieuw event', (string) ($updated->name ?? ''));
        $this->assertSame('2026-09-15', (string) ($updated->event_date ?? ''));
        $meta = json_decode((string) ($updated->meta_data ?? ''), true);
        $this->assertSame(18, (int) ($meta['max_teams'] ?? 0));
    }

    /** @test */
    public function it_lists_only_eligible_parts_for_event_linking(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) ['id' => 21, 'name' => 'Doel', 'status' => 'concept']);
        $events->seed((object) ['id' => 22, 'name' => 'Ander actief', 'status' => 'actief']);
        $events->seed((object) ['id' => 23, 'name' => 'Ander gesloten', 'status' => 'afgesloten']);

        $parts = new InMemoryPartAdminRepository();
        $parts->seed((object) ['id' => 501, 'name' => 'Kanovaren', 'event_id' => 21, 'status' => 'actief']);
        $parts->seed((object) ['id' => 502, 'name' => 'Klimnet', 'event_id' => null, 'status' => 'actief']);
        $parts->seed((object) ['id' => 503, 'name' => 'Vlotbouwen', 'event_id' => 22, 'status' => 'actief']);
        $parts->seed((object) ['id' => 504, 'name' => 'KanoVAREN', 'event_id' => 23, 'status' => 'actief']);
        $parts->seed((object) ['id' => 505, 'name' => 'Tokkelbaan', 'event_id' => 23, 'status' => 'actief']);
        $parts->seed((object) ['id' => 506, 'name' => 'Verborgen', 'event_id' => null, 'status' => 'verwijderd']);

        $service = new EventAdminService(
            $events,
            new InMemoryEventAdminRepository($events),
            $parts,
            new InMemoryEventPublicationRepository()
        );

        $eligible = $service->listEligiblePartsForEvent(21);
        $eligibleIds = array_map(static function ($part): int {
            return (int) ($part->id ?? 0);
        }, $eligible);

        $this->assertSame([501, 502, 505], $eligibleIds);

        $filtered = $service->listEligiblePartsForEvent(21, 'tok');
        $this->assertCount(1, $filtered);
        $this->assertSame(505, (int) ($filtered[0]->id ?? 0));
    }

    /** @test */
    public function it_lists_assigned_parts_for_read_only_event_views(): void {
        $events = new InMemoryEventReadRepository();
        $events->seed((object) ['id' => 31, 'name' => 'Read only event', 'status' => 'gesloten']);

        $parts = new InMemoryPartAdminRepository();
        $parts->seed((object) ['id' => 701, 'name' => 'Vlotbouwen', 'event_id' => 31, 'status' => 'actief']);
        $parts->seed((object) ['id' => 702, 'name' => 'Klimnet', 'event_id' => 31, 'status' => 'inactief']);
        $parts->seed((object) ['id' => 703, 'name' => 'Niet zichtbaar', 'event_id' => 31, 'status' => 'verwijderd']);
        $parts->seed((object) ['id' => 704, 'name' => 'Ander event', 'event_id' => 32, 'status' => 'actief']);

        $service = new EventAdminService(
            $events,
            new InMemoryEventAdminRepository($events),
            $parts,
            new InMemoryEventPublicationRepository()
        );

        $all = $service->listAssignedPartsForEvent(31);
        $allIds = array_map(static function ($part): int {
            return (int) ($part->id ?? 0);
        }, $all);
        $this->assertSame([702, 701], $allIds);

        $filtered = $service->listAssignedPartsForEvent(31, 'vlot');
        $this->assertCount(1, $filtered);
        $this->assertSame(701, (int) ($filtered[0]->id ?? 0));
    }

    /** @test */
    public function it_builds_fixed_timeslot_matrix_with_hard_pause_slot(): void {
        $service = new EventAdminService(
            new InMemoryEventReadRepository(),
            new InMemoryEventAdminRepository(new InMemoryEventReadRepository()),
            new InMemoryPartAdminRepository(),
            new InMemoryEventPublicationRepository()
        );

        $method = new \ReflectionMethod(EventAdminService::class, 'buildFixedTimeslotWindows');
        $method->setAccessible(true);

        /** @var array<int, array<string, mixed>> $slots */
        $slots = $method->invoke($service, '2026-10-05');

        $this->assertCount(14, $slots);
        $this->assertSame('2026-10-05 09:00:00', (string) ($slots[0]['start_at'] ?? ''));
        $this->assertSame('2026-10-05 09:30:00', (string) ($slots[0]['end_at'] ?? ''));

        $this->assertSame('2026-10-05 12:05:00', (string) ($slots[5]['start_at'] ?? ''));
        $this->assertSame('2026-10-05 12:35:00', (string) ($slots[5]['end_at'] ?? ''));
        $this->assertTrue((bool) ($slots[5]['is_pause'] ?? false));

        $this->assertSame('2026-10-05 16:45:00', (string) ($slots[13]['start_at'] ?? ''));
        $this->assertSame('2026-10-05 17:15:00', (string) ($slots[13]['end_at'] ?? ''));
    }

    /** @test */
    public function it_generates_full_round_robin_round_count_for_even_team_pool(): void {
        $service = new EventAdminService(
            new InMemoryEventReadRepository(),
            new InMemoryEventAdminRepository(new InMemoryEventReadRepository()),
            new InMemoryPartAdminRepository(),
            new InMemoryEventPublicationRepository()
        );

        $teams = [
            (object) ['id' => 1],
            (object) ['id' => 2],
            (object) ['id' => 3],
            (object) ['id' => 4],
            (object) ['id' => 5],
            (object) ['id' => 6],
        ];

        $method = new \ReflectionMethod(EventAdminService::class, 'buildRoundRobinPairs');
        $method->setAccessible(true);

        /** @var array<int, array<int, array<int, object>>> $rounds */
        $rounds = $method->invoke($service, $teams);

        $this->assertCount(5, $rounds);
        foreach ($rounds as $round) {
            $this->assertCount(3, $round);
        }
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

    public function updateById(int $eventId, array $data) {
        $row = $this->events->findById($eventId);
        if ($row === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            $row->{$key} = $value;
        }

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

    public function findById(int $partId) {
        return $this->rows[$partId] ?? null;
    }

    public function create(array $data) {
        $id = count($this->rows) + 1;
        $row = (object) array_merge(['id' => $id], $data);
        $this->rows[$id] = $row;
        return $row;
    }

    public function updateById(int $partId, array $data) {
        if (!isset($this->rows[$partId])) {
            return null;
        }

        foreach ($data as $key => $value) {
            $this->rows[$partId]->{$key} = $value;
        }

        return $this->rows[$partId];
    }

    public function markDeleted(int $partId): bool {
        if (!isset($this->rows[$partId])) {
            return false;
        }

        $this->rows[$partId]->status = 'verwijderd';
        $this->rows[$partId]->event_id = null;
        return true;
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
