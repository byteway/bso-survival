<?php

namespace BSO\Survival\Database\Repository;

interface PartAdminRepositoryInterface {
    /**
     * @return array<int, object>
     */
    public function findAll(): array;

    /**
     * @param array<int, int> $partIds
     * @return array<int, object>
     */
    public function findByIds(array $partIds): array;

    /**
     * @return array<int, object>
     */
    public function findByEventId(int $eventId): array;

    /** @return object|null */
    public function findById(int $partId);

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function create(array $data);

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function updateById(int $partId, array $data);

    public function markDeleted(int $partId): bool;

    public function assignToEvent(int $partId, ?int $eventId): bool;
}
