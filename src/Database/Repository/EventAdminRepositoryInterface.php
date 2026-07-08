<?php

namespace BSO\Survival\Database\Repository;

interface EventAdminRepositoryInterface {
    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function create(array $data);

    public function markDeleted(int $eventId): bool;
}
