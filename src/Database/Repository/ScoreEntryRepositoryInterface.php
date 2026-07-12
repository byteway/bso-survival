<?php

namespace BSO\Survival\Database\Repository;

interface ScoreEntryRepositoryInterface {
    /**
     * @return object|null
     */
    public function findById(int $id);

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data);

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function updateById(int $id, array $data);

    /**
     * @return array<int, float>
     */
    public function findLatestRawValuesByPart(int $eventId, int $partId): array;

    /**
     * @return array<int, float>
     */
    public function findLatestNormalizedPointsByPart(int $eventId, int $partId): array;

    /**
     * @param array<int, int> $assignmentIds
     * @return array<int, int>
     */
    public function findAssignmentIdsWithEntries(array $assignmentIds): array;
}
