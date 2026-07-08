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

    public function assignToEvent(int $partId, ?int $eventId): bool;
}
