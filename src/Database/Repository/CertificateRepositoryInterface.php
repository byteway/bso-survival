<?php

namespace BSO\Survival\Database\Repository;

interface CertificateRepositoryInterface {
    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data);
}
