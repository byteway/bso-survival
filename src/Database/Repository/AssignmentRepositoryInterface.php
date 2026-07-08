<?php

namespace BSO\Survival\Database\Repository;

interface AssignmentRepositoryInterface {
    /**
     * @return object|null
     */
    public function findById(int $id);

    /**
     * @return array<int, object>
     */
    public function findByEventId(int $eventId): array;
}
