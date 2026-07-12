<?php

namespace BSO\Survival\Database\Repository;

interface DashboardWidgetLayoutRepositoryInterface {
    /**
     * @return array<string, mixed>
     */
    public function getByEventId(int $eventId): array;

    /**
     * @param array<string, mixed> $layout
     */
    public function saveByEventId(int $eventId, array $layout): void;
}
