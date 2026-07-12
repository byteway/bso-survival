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

    /**
     * @return array<int, object>
     */
    public function findByTeamId(int $teamId): array;

    public function deleteByTeamId(int $teamId): int;

    /**
     * @param array<int, string> $names
     */
    public function replaceForTeam(int $teamId, array $names): int;
}
