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
    public function it_blocks_delete_when_part_is_used_in_active_event(): void {
        $events = new PartAdminInMemoryEventRepository();
        $events->seed((object) ['id' => 91, 'status' => 'actief']);
        $parts = new PartAdminInMemoryPartRepository();
        $parts->seed((object) ['id' => 11, 'name' => 'Touwbrug', 'event_id' => 91, 'status' => 'actief']);

        $service = new PartAdminService($parts, $events);

        $this->expectException(RuntimeException::class);
        $service->deletePart(11);
    }

    /** @test */
    public function it_marks_part_deleted_when_only_closed_event_uses_it(): void {
        $events = new PartAdminInMemoryEventRepository();
        $events->seed((object) ['id' => 92, 'status' => 'afgesloten']);
        $parts = new PartAdminInMemoryPartRepository();
        $parts->seed((object) ['id' => 12, 'name' => 'Netklim', 'event_id' => 92, 'status' => 'actief']);

        $service = new PartAdminService($parts, $events);

        $this->assertTrue($service->deletePart(12));
        $deleted = $parts->findById(12);
        $this->assertSame('verwijderd', (string) ($deleted->status ?? ''));
        $this->assertNull($deleted->event_id ?? null);
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
