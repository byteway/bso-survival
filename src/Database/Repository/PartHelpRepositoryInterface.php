<?php

namespace BSO\Survival\Database\Repository;

interface PartHelpRepositoryInterface {
    /** @return object|null */
    public function findByPartId(int $partId);

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function upsertByPartId(int $partId, array $data);
}
