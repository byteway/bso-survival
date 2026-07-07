<?php

namespace BSO\Survival\Database\Repository;

interface AuditLogRepositoryInterface {
    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data);
}
