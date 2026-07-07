<?php

namespace BSO\Survival\Database\Repository;

interface PartRuleRepositoryInterface {
    /**
     * @return object|null
     */
    public function findByPartId(int $partId);

    /**
     * @return array<int, object>
     */
    public function findByEventId(int $eventId): array;

    public function upsertForPart(int $partId, string $scoringMode, string $unit, string $tiebreakerMode, string $scoringConfig): bool;
}
