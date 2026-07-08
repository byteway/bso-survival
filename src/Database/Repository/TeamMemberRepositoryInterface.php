<?php

namespace BSO\Survival\Database\Repository;

interface TeamMemberRepositoryInterface {
    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function create(array $data);

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return int
     */
    public function createBatch(array $rows): int;
}
