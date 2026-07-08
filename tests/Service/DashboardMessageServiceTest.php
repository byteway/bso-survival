<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\DashboardMessageRepositoryInterface;
use BSO\Survival\Service\DashboardMessageService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DashboardMessageServiceTest extends TestCase {
    /** @test */
    public function it_creates_global_message_with_global_visibility(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $service = new DashboardMessageService($repo);

        $created = $service->create([
            'event_id' => 8,
            'type' => 'urgent',
            'text' => 'Global alarm',
            'scope' => 'global',
            'status' => 'actief',
        ]);

        $this->assertSame('global', $created->visibility);
        $this->assertSame('urgent', $created->type);
        $this->assertSame('actief', $created->status);
    }

    /** @test */
    public function it_persists_message_meta_data_as_json(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $service = new DashboardMessageService($repo);

        $created = $service->create([
            'event_id' => 8,
            'type' => 'info',
            'text' => 'Met metadata',
            'scope' => 'event',
            'status' => 'actief',
            'meta_data' => [
                'channel' => 'operations',
                'sticky' => true,
            ],
        ]);

        $this->assertIsString($created->meta_data);
        $this->assertSame('{"channel":"operations","sticky":true}', $created->meta_data);
    }

    /** @test */
    public function it_forwards_scope_to_repository_when_listing(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $service = new DashboardMessageService($repo);

        $service->listForEvent(5, 25, 'global');

        $this->assertSame(5, $repo->lastFindEventId);
        $this->assertSame('global', $repo->lastFindScope);
        $this->assertSame(false, $repo->lastFindActiveOnly);
        $this->assertSame(25, $repo->lastFindLimit);
    }

    /** @test */
    public function it_lists_paginated_messages_with_total_count(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $repo->setCountByScope(37);
        $service = new DashboardMessageService($repo);

        $result = $service->listPageForEvent(5, 3, 10, 'global');

        $this->assertSame(5, $repo->lastFindEventId);
        $this->assertSame('global', $repo->lastFindScope);
        $this->assertSame(10, $repo->lastFindLimit);
        $this->assertSame(20, $repo->lastFindOffset);
        $this->assertSame(37, $result['total']);
        $this->assertSame(3, $result['page']);
        $this->assertSame(10, $result['per_page']);
    }

    /** @test */
    public function it_rejects_invalid_per_page_in_paginated_listing(): void {
        $service = new DashboardMessageService(new InMemoryDashboardMessageRepository());

        $this->expectException(InvalidArgumentException::class);
        $service->listPageForEvent(5, 1, 0, 'all');
    }

    /** @test */
    public function it_updates_global_message_status_without_event_constraint(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $repo->seed((object) [
            'id' => 10,
            'event_id' => 3,
            'type' => 'info',
            'text' => 'Global',
            'visibility' => 'global',
            'status' => 'actief',
        ]);

        $service = new DashboardMessageService($repo);
        $updated = $service->setStatus(10, 3, 'inactief', 'admin');

        $this->assertSame('inactief', $updated->status);
        $this->assertSame(0, $repo->lastStatusEventId);
    }

    /** @test */
    public function it_rejects_invalid_scope_value(): void {
        $service = new DashboardMessageService(new InMemoryDashboardMessageRepository());

        $this->expectException(InvalidArgumentException::class);
        $service->create([
            'event_id' => 8,
            'type' => 'info',
            'text' => 'abc',
            'scope' => 'unknown',
            'status' => 'actief',
        ]);
    }

    /** @test */
    public function it_rejects_invalid_message_meta_data(): void {
        $service = new DashboardMessageService(new InMemoryDashboardMessageRepository());

        $this->expectException(InvalidArgumentException::class);
        $service->create([
            'event_id' => 8,
            'type' => 'info',
            'text' => 'abc',
            'scope' => 'event',
            'status' => 'actief',
            'meta_data' => '{invalid',
        ]);
    }
}

class InMemoryDashboardMessageRepository implements DashboardMessageRepositoryInterface {
    /** @var array<int, object> */
    private $rows = [];

    /** @var int */
    private $nextId = 1;

    /** @var int */
    public $lastFindEventId = 0;

    /** @var string */
    public $lastFindScope = 'all';

    /** @var bool */
    public $lastFindActiveOnly = false;

    /** @var int */
    public $lastFindLimit = 0;

    /** @var int */
    public $lastFindOffset = 0;

    /** @var int */
    public $lastStatusEventId = -1;

    /** @var int */
    private $countByScope = 0;

    public function findByEventId(int $eventId, int $limit = 20): array {
        return $this->findByScope($eventId, 'all', false, $limit);
    }

    public function findByScope(int $eventId, string $scope = 'all', bool $activeOnly = false, int $limit = 20, int $offset = 0): array {
        $this->lastFindEventId = $eventId;
        $this->lastFindScope = $scope;
        $this->lastFindActiveOnly = $activeOnly;
        $this->lastFindLimit = $limit;
        $this->lastFindOffset = $offset;

        return [];
    }

    public function countByScope(int $eventId, string $scope = 'all', bool $activeOnly = false): int {
        $this->lastFindEventId = $eventId;
        $this->lastFindScope = $scope;
        $this->lastFindActiveOnly = $activeOnly;

        return $this->countByScope;
    }

    public function findActiveByEventId(int $eventId, int $limit = 5): array {
        return $this->findByScope($eventId, 'all', true, $limit);
    }

    public function findById(int $id) {
        return $this->rows[$id] ?? null;
    }

    public function create(array $data) {
        $id = $this->nextId++;
        $row = (object) array_merge(['id' => $id], $data);
        $this->rows[$id] = $row;
        return $row;
    }

    public function updateStatus(int $id, string $status) {
        return $this->updateStatusForEvent($id, 0, $status);
    }

    public function updateStatusForEvent(int $id, int $eventId, string $status) {
        $this->lastStatusEventId = $eventId;

        if (!isset($this->rows[$id])) {
            return null;
        }

        if ($eventId > 0 && (int) ($this->rows[$id]->event_id ?? 0) !== $eventId) {
            return null;
        }

        $this->rows[$id]->status = $status;
        return $this->rows[$id];
    }

    public function seed(object $row): void {
        $id = (int) ($row->id ?? 0);
        if ($id <= 0) {
            $id = $this->nextId++;
            $row->id = $id;
        }

        $this->rows[$id] = $row;
        if ($id >= $this->nextId) {
            $this->nextId = $id + 1;
        }
    }

    public function setCountByScope(int $count): void {
        $this->countByScope = $count;
    }
}
