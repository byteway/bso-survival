<?php

namespace BSO\Survival\Database\Repository;

interface EmailOutboxRepositoryInterface {
    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data);

    /** @return object|null */
    public function findByDedupeKey(string $dedupeKey);

    /**
     * @return array<int, object>
     */
    public function findDue(string $nowUtc, int $limit): array;

    public function markSent(int $id, string $sentAtUtc): bool;

    public function markForRetry(int $id, int $attemptCount, string $nextAttemptAtUtc, string $lastError): bool;

    public function markFailed(int $id, int $attemptCount, string $lastError): bool;
}
