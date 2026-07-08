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
    public function it_persists_visibility_window_on_create(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $service = new DashboardMessageService($repo);

        $created = $service->create([
            'event_id' => 8,
            'type' => 'info',
            'text' => 'Met zichtvenster',
            'scope' => 'event',
            'status' => 'actief',
            'visible_from' => '2026-07-08T10:00',
            'visible_until' => '2026-07-08T11:00',
        ]);

        $this->assertSame('2026-07-08 10:00:00', $created->visible_from);
        $this->assertSame('2026-07-08 11:00:00', $created->visible_until);
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
    public function it_lists_advanced_paginated_messages_with_normalized_filters(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $repo->setCountByAdvancedFilters(12);
        $service = new DashboardMessageService($repo);

        $result = $service->listAdvancedPageForEvent(5, [
            'scope' => 'global',
            'status' => 'actief',
            'type' => 'warning',
            'visible_at' => '2026-07-08T10:15',
            'search' => 'briefing',
        ], 2, 15);

        $this->assertSame(5, $repo->lastAdvancedEventId);
        $this->assertSame(15, $repo->lastAdvancedLimit);
        $this->assertSame(15, $repo->lastAdvancedOffset);
        $this->assertSame('global', $repo->lastAdvancedFilters['scope']);
        $this->assertSame('actief', $repo->lastAdvancedFilters['status']);
        $this->assertSame('warning', $repo->lastAdvancedFilters['type']);
        $this->assertSame('briefing', $repo->lastAdvancedFilters['search']);
        $this->assertSame('2026-07-08 10:15:00', $repo->lastAdvancedFilters['visible_at']);
        $this->assertSame(12, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(15, $result['per_page']);
    }

    /** @test */
    public function it_rejects_invalid_advanced_filter_values(): void {
        $service = new DashboardMessageService(new InMemoryDashboardMessageRepository());

        $this->expectException(InvalidArgumentException::class);
        $service->listAdvancedPageForEvent(5, [
            'scope' => 'invalid-scope',
        ], 1, 20);
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
    public function it_bulk_updates_message_status_for_event_messages(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $repo->seed((object) [
            'id' => 21,
            'event_id' => 5,
            'status' => 'actief',
            'visibility' => 'intern',
        ]);
        $repo->seed((object) [
            'id' => 22,
            'event_id' => 5,
            'status' => 'actief',
            'visibility' => 'intern',
        ]);

        $service = new DashboardMessageService($repo);
        $result = $service->bulkSetStatusForEvent(5, [21, 22], 'inactief', 'planner');

        $this->assertSame(2, $result['updated_count']);
        $this->assertSame([21, 22], $result['updated_ids']);
        $this->assertSame('inactief', (string) $repo->findById(21)->status);
        $this->assertSame('inactief', (string) $repo->findById(22)->status);
    }

    /** @test */
    public function it_rejects_bulk_update_when_message_ids_do_not_match_event(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $repo->seed((object) [
            'id' => 30,
            'event_id' => 9,
            'status' => 'actief',
            'visibility' => 'intern',
        ]);

        $service = new DashboardMessageService($repo);

        $this->expectException(\RuntimeException::class);
        $service->bulkSetStatusForEvent(5, [30], 'inactief', 'planner');
    }

    /** @test */
    public function it_rejects_bulk_update_with_empty_message_ids(): void {
        $service = new DashboardMessageService(new InMemoryDashboardMessageRepository());

        $this->expectException(InvalidArgumentException::class);
        $service->bulkSetStatusForEvent(5, [], 'inactief', 'planner');
    }

    /** @test */
    public function it_updates_message_content_and_status(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $repo->seed((object) [
            'id' => 11,
            'event_id' => 3,
            'type' => 'info',
            'text' => 'Oud',
            'visibility' => 'intern',
            'status' => 'actief',
            'meta_data' => null,
        ]);

        $service = new DashboardMessageService($repo);
        $updated = $service->update(11, 3, [
            'type' => 'warning',
            'text' => 'Nieuw bericht',
            'status' => 'inactief',
            'scope' => 'event',
            'visible_from' => '2026-07-08T12:00',
            'visible_until' => '2026-07-08T13:00',
        ], 'beheer');

        $this->assertSame('warning', $updated->type);
        $this->assertSame('Nieuw bericht', $updated->text);
        $this->assertSame('inactief', $updated->status);
        $this->assertSame('2026-07-08 12:00:00', $updated->visible_from);
        $this->assertSame('2026-07-08 13:00:00', $updated->visible_until);
        $this->assertSame(3, $repo->lastUpdateEventId);
    }

    /** @test */
    public function it_rejects_invalid_visibility_window_order(): void {
        $service = new DashboardMessageService(new InMemoryDashboardMessageRepository());

        $this->expectException(InvalidArgumentException::class);
        $service->create([
            'event_id' => 8,
            'type' => 'info',
            'text' => 'abc',
            'scope' => 'event',
            'status' => 'actief',
            'visible_from' => '2026-07-08T11:00',
            'visible_until' => '2026-07-08T10:00',
        ]);
    }

    /** @test */
    public function it_deletes_message_for_event(): void {
        $repo = new InMemoryDashboardMessageRepository();
        $repo->seed((object) [
            'id' => 12,
            'event_id' => 3,
            'type' => 'info',
            'text' => 'Delete me',
            'visibility' => 'intern',
            'status' => 'actief',
        ]);

        $service = new DashboardMessageService($repo);
        $deleted = $service->delete(12, 3, 'beheer');

        $this->assertTrue($deleted);
        $this->assertSame(12, $repo->lastDeletedId);
        $this->assertSame(3, $repo->lastDeleteEventId);
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
    public $lastUpdateEventId = -1;

    /** @var int */
    public $lastDeleteEventId = -1;

    /** @var int */
    public $lastDeletedId = 0;

    /** @var int */
    private $countByScope = 0;

    /** @var int */
    public $lastAdvancedEventId = 0;

    /** @var array<string, mixed> */
    public $lastAdvancedFilters = [];

    /** @var int */
    public $lastAdvancedLimit = 0;

    /** @var int */
    public $lastAdvancedOffset = 0;

    /** @var int */
    private $countByAdvancedFilters = 0;

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

    public function findByAdvancedFilters(int $eventId, array $filters, int $limit = 20, int $offset = 0): array {
        $this->lastAdvancedEventId = $eventId;
        $this->lastAdvancedFilters = $filters;
        $this->lastAdvancedLimit = $limit;
        $this->lastAdvancedOffset = $offset;

        return [];
    }

    public function countByAdvancedFilters(int $eventId, array $filters): int {
        $this->lastAdvancedEventId = $eventId;
        $this->lastAdvancedFilters = $filters;

        return $this->countByAdvancedFilters;
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

    public function updateById(int $id, array $data) {
        return $this->updateByIdForEvent($id, 0, $data);
    }

    public function updateByIdForEvent(int $id, int $eventId, array $data) {
        $this->lastUpdateEventId = $eventId;

        if (!isset($this->rows[$id])) {
            return null;
        }

        if ($eventId > 0 && (int) ($this->rows[$id]->event_id ?? 0) !== $eventId) {
            return null;
        }

        $this->rows[$id] = (object) array_merge((array) $this->rows[$id], $data);
        return $this->rows[$id];
    }

    public function deleteById(int $id): bool {
        return $this->deleteByIdForEvent($id, 0);
    }

    public function deleteByIdForEvent(int $id, int $eventId): bool {
        $this->lastDeletedId = $id;
        $this->lastDeleteEventId = $eventId;

        if (!isset($this->rows[$id])) {
            return false;
        }

        if ($eventId > 0 && (int) ($this->rows[$id]->event_id ?? 0) !== $eventId) {
            return false;
        }

        unset($this->rows[$id]);
        return true;
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

    public function setCountByAdvancedFilters(int $count): void {
        $this->countByAdvancedFilters = $count;
    }
}
