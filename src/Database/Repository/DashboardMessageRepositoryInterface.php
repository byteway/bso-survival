<?php

namespace BSO\Survival\Database\Repository;

interface DashboardMessageRepositoryInterface {
    /**
     * @return array<int, object>
     */
    public function findByEventId(int $eventId, int $limit = 20): array;

    /**
     * @return array<int, object>
     */
    public function findByScope(int $eventId, string $scope = 'all', bool $activeOnly = false, int $limit = 20): array;

    /**
     * @return array<int, object>
     */
    public function findActiveByEventId(int $eventId, int $limit = 5): array;

    /**
     * @return object|null
     */
    public function findById(int $id);

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function create(array $data);

    /**
     * @return object|null
     */
    public function updateStatus(int $id, string $status);

    /**
     * @return object|null
     */
    public function updateStatusForEvent(int $id, int $eventId, string $status);
}
