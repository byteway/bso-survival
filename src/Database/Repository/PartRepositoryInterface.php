<?php

namespace BSO\Survival\Database\Repository;

interface PartRepositoryInterface {
    /**
     * @return object|null
     */
    public function findById(int $id);

    /**
     * @return array<int, object>
     */
    public function findByEventId(int $eventId): array;

    public function countByEventId(int $eventId): int;
}
