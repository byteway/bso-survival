<?php

namespace BSO\Survival\Database\Repository;

interface EventRepositoryInterface {
    /**
     * @return array<int, object>
     */
    public function findAll(): array;

    /**
     * @return object|null
     */
    public function findById(int $id);

    /**
     * @return array<int, object>
     */
    public function findByStatus(string $status): array;
}
