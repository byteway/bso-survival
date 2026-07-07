<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\DashboardWidgetLayoutRepositoryInterface;
use InvalidArgumentException;

class DashboardWidgetLayoutService {
    /** @var DashboardWidgetLayoutRepositoryInterface */
    private $repository;

    public function __construct(DashboardWidgetLayoutRepositoryInterface $repository) {
        $this->repository = $repository;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getLayoutForEvent(int $eventId): array {
        $this->guardPositiveId($eventId, 'event id');

        $storedLayout = $this->repository->getByEventId($eventId);
        return $this->sanitizeLayout($storedLayout);
    }

    /**
     * @param array<string, array<int, string>> $layout
     * @return array<string, array<int, string>>
     */
    public function saveLayoutForEvent(int $eventId, array $layout): array {
        $this->guardPositiveId($eventId, 'event id');

        $sanitized = $this->sanitizeLayout($layout);
        $this->repository->saveByEventId($eventId, $sanitized);

        return $sanitized;
    }

    /**
     * @param array<string, array<int, string>> $layout
     * @return array<string, array<int, string>>
     */
    private function sanitizeLayout(array $layout): array {
        $availableBySection = $this->getAvailableWidgetIdsBySection();
        $sanitized = [];

        foreach ($availableBySection as $section => $availableIds) {
            $requested = $layout[$section] ?? null;

            // Missing section in persisted layout means fallback to current defaults.
            if (!is_array($requested)) {
                $sanitized[$section] = $availableIds;
                continue;
            }

            $allowed = array_fill_keys($availableIds, true);
            $cleanIds = [];
            foreach ($requested as $id) {
                if (!is_string($id)) {
                    continue;
                }

                $cleanId = trim($id);
                if ($cleanId === '' || !isset($allowed[$cleanId])) {
                    continue;
                }

                $cleanIds[$cleanId] = $cleanId;
            }

            // Explicit empty selection means the section is disabled for the event.
            $sanitized[$section] = array_values($cleanIds);
        }

        return $sanitized;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function getAvailableWidgetIdsBySection(): array {
        if (DashboardWidgetRegistry::getSectionIds() === []) {
            DashboardWidgetRegistry::initDefaults();
        }

        $map = [];
        foreach (DashboardWidgetRegistry::getSectionIds() as $section) {
            $map[$section] = DashboardWidgetRegistry::getSectionWidgetIds($section);
        }

        return $map;
    }

    private function guardPositiveId(int $id, string $label): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $label));
        }
    }
}
