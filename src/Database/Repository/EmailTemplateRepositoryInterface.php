<?php

namespace BSO\Survival\Database\Repository;

interface EmailTemplateRepositoryInterface {
    /** @return object|null */
    public function findByKey(string $templateKey);

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function upsertByKey(string $templateKey, array $data);
}
