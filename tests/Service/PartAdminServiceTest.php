<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Database\Repository\PartAdminRepositoryInterface;
use BSO\Survival\Service\PartAdminService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PartAdminServiceTest extends TestCase {
    /** @test */
    public function it_creates_and_updates_part_records(): void {
        $events = new PartAdminInMemoryEventRepository();
        $parts = new PartAdminInMemoryPartRepository();
        $service = new PartAdminService($parts, $events);

        $created = $service->createPart([
            'name' => 'Kanovaren',
            'status' => 'actief',
            'latitude' => '52.1234',
            'longitude' => '4.4321',
            'meta_data' => ['difficulty' => 'medium'],
        ]);

        $this->assertGreaterThan(0, (int) ($created->id ?? 0));
        $this->assertSame('Kanovaren', (string) ($created->name ?? ''));

        $updated = $service->updatePart((int) $created->id, [
            'name' => 'Kanovaren XL',
            'status' => 'inactief',
        ]);

        $this->assertSame('Kanovaren XL', (string) ($updated->name ?? ''));
        $this->assertSame('inactief', (string) ($updated->status ?? ''));
    }

    /** @test */
    public function it_deactivates_part_when_it_is_linked_to_an_active_event(): void {
        $events = new PartAdminInMemoryEventRepository();
        $events->seed((object) ['id' => 91, 'status' => 'actief']);
        $parts = new PartAdminInMemoryPartRepository();
        $parts->seed((object) ['id' => 11, 'name' => 'Touwbrug', 'event_id' => 91, 'status' => 'actief']);

        $service = new PartAdminService($parts, $events);

        $result = $service->deletePart(11);
        $this->assertSame('deactivated', $result['saved']);
        $this->assertStringContainsString('Touwbrug', $result['message']);

        $updated = $parts->findById(11);
        $this->assertSame('inactief', (string) ($updated->status ?? ''));
        $this->assertSame(91, (int) ($updated->event_id ?? 0));
    }

    /** @test */
    public function it_marks_part_deleted_when_only_closed_event_uses_it(): void {
        $events = new PartAdminInMemoryEventRepository();
        $events->seed((object) ['id' => 92, 'status' => 'afgesloten']);
        $parts = new PartAdminInMemoryPartRepository();
        $parts->seed((object) ['id' => 12, 'name' => 'Netklim', 'event_id' => 92, 'status' => 'actief']);

        $service = new PartAdminService($parts, $events);

        $result = $service->deletePart(12);
        $this->assertSame('deactivated', $result['saved']);
        $this->assertStringContainsString('Netklim', $result['message']);

        $updated = $parts->findById(12);
        $this->assertSame('inactief', (string) ($updated->status ?? ''));
        $this->assertSame(92, (int) ($updated->event_id ?? 0));
    }

    /** @test */
    public function it_imports_json_and_exports_reusable_json(): void {
        $service = new PartAdminService(new PartAdminInMemoryPartRepository(), new PartAdminInMemoryEventRepository());

        $created = $service->importParts(json_encode([
            [
                'name' => 'Klimwand',
                'status' => 'actief',
                'latitude' => 51.9,
                'longitude' => 4.4,
                'meta_data' => ['help' => 'handschoenen verplicht'],
            ],
            [
                'name' => 'Vlotbouwen',
                'status' => 'inactief',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->assertCount(2, $created);

        $json = $service->exportParts();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertSame('Klimwand', $decoded[0]['name']);
    }

    /** @test */
    public function it_rejects_duplicate_names_during_import(): void {
        $service = new PartAdminService(new PartAdminInMemoryPartRepository(), new PartAdminInMemoryEventRepository());

        $this->expectException(InvalidArgumentException::class);
        $service->importParts('[{"name":"Klimwand"},{"name":"Klimwand"}]');
    }

    /** @test */
    public function it_sorts_parts_by_requested_admin_column(): void {
        $parts = new PartAdminInMemoryPartRepository();
        $parts->seed((object) ['id' => 7, 'name' => 'Tokkelbaan', 'status' => 'inactief', 'event_id' => 15]);
        $parts->seed((object) ['id' => 3, 'name' => 'Boogschieten', 'status' => 'actief', 'event_id' => null]);
        $parts->seed((object) ['id' => 9, 'name' => 'Kanovaren', 'status' => 'actief', 'event_id' => 4]);

        $service = new PartAdminService($parts, new PartAdminInMemoryEventRepository());

        $byId = array_map(static function ($part): int {
            return (int) ($part->id ?? 0);
        }, $service->listPartsSorted('id', 'asc'));
        $this->assertSame([3, 7, 9], $byId);

        $byNameDesc = array_map(static function ($part): string {
            return (string) ($part->name ?? '');
        }, $service->listPartsSorted('name', 'desc'));
        $this->assertSame(['Tokkelbaan', 'Kanovaren', 'Boogschieten'], $byNameDesc);

        $byEvent = array_map(static function ($part): int {
            return (int) ($part->event_id ?? 0);
        }, $service->listPartsSorted('event_id', 'asc'));
        $this->assertSame([0, 4, 15], $byEvent);
    }

    /** @test */
    public function it_filters_parts_by_search_term_before_sorting(): void {
        $parts = new PartAdminInMemoryPartRepository();
        $parts->seed((object) ['id' => 12, 'name' => 'Kanovaren', 'status' => 'actief', 'event_id' => 8]);
        $parts->seed((object) ['id' => 4, 'name' => 'Klimwand', 'status' => 'inactief', 'event_id' => null]);
        $parts->seed((object) ['id' => 21, 'name' => 'Touwbrug', 'status' => 'actief', 'event_id' => 18]);

        $service = new PartAdminService($parts, new PartAdminInMemoryEventRepository());

        $byName = array_map(static function ($part): string {
            return (string) ($part->name ?? '');
        }, $service->listPartsFilteredSorted('k', 'name', 'asc'));
        $this->assertSame(['Kanovaren', 'Klimwand'], $byName);

        $byEventId = array_map(static function ($part): int {
            return (int) ($part->id ?? 0);
        }, $service->listPartsFilteredSorted('18', 'id', 'asc'));
        $this->assertSame([21], $byEventId);
    }
}

class PartAdminInMemoryEventRepository implements EventRepositoryInterface {
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
        $this->rows[(int) $row->id] = $row;
    }
}

class PartAdminInMemoryPartRepository implements PartAdminRepositoryInterface {
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
        $this->rows[$id] = $row;
    }
}
