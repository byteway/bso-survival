<?php

namespace BSO\Survival\Database\Repository;

interface TeamRepositoryInterface {
    /**
     * @return object|null
     */
    public function findById(int $id);

    /**
     * @return array<int, object>
     */
    public function findByEventId(int $eventId): array;

    public function countByEventId(int $eventId): int;

    /** @return object|null */
    public function findByEventIdAndName(int $eventId, string $name);

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function create(array $data);
}
