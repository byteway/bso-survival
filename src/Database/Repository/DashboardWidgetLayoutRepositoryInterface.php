<?php

namespace BSO\Survival\Database\Repository;

interface DashboardWidgetLayoutRepositoryInterface {
    /**
     * @return array<string, array<int, string>>
     */
    public function getByEventId(int $eventId): array;

    /**
     * @param array<string, array<int, string>> $layout
     */
    public function saveByEventId(int $eventId, array $layout): void;
}
