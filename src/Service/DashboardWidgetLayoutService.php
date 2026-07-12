<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\DashboardWidgetLayoutRepositoryInterface;
use InvalidArgumentException;

class DashboardWidgetLayoutService {
    private const DEFAULT_WIDTH = '1/4';

    /** @var array<string, int> */
    private const WIDTH_SPANS = [
        '1/4' => 1,
        '1/5' => 2,
        '3/4' => 3,
        '1' => 4,
    ];

    /** @var DashboardWidgetLayoutRepositoryInterface */
    private $repository;

    public function __construct(DashboardWidgetLayoutRepositoryInterface $repository) {
        $this->repository = $repository;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLayoutForEvent(int $eventId): array {
        $this->guardPositiveId($eventId, 'event id');

        $storedLayout = $this->repository->getByEventId($eventId);
        return $this->sanitizeLayout($storedLayout);
    }

    /**
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    public function saveLayoutForEvent(int $eventId, array $layout): array {
        $this->guardPositiveId($eventId, 'event id');

        $sanitized = $this->sanitizeLayout($layout);
        $this->repository->saveByEventId($eventId, $sanitized);

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    private function sanitizeLayout(array $layout): array {
        $availableBySection = $this->getAvailableWidgetIdsBySection();
        $sanitized = [];
        $sanitizedWidths = [];
        $requestedWidths = isset($layout['widths']) && is_array($layout['widths']) ? $layout['widths'] : [];
        $messageWasRequestedInOperations = isset($layout['operations'])
            && is_array($layout['operations'])
            && in_array('message_widget', $layout['operations'], true);

        foreach ($availableBySection as $section => $availableIds) {
            $requested = $layout[$section] ?? null;
            $sectionWidths = isset($requestedWidths[$section]) && is_array($requestedWidths[$section]) ? $requestedWidths[$section] : [];

            $sanitizedWidths[$section] = [];
            foreach ($availableIds as $widgetId) {
                $requestedWidth = $sectionWidths[$widgetId] ?? self::getDefaultWidthForWidget($widgetId);
                $sanitizedWidths[$section][$widgetId] = $this->normalizeWidth($requestedWidth, $widgetId);
            }

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

        // Legacy migration: message_widget moved from operations to main.
        if ($messageWasRequestedInOperations) {
            $mainIds = isset($sanitized['main']) && is_array($sanitized['main']) ? $sanitized['main'] : [];
            if (!in_array('message_widget', $mainIds, true)) {
                $timeslotIndex = array_search('timeslot_progress', $mainIds, true);
                if ($timeslotIndex === false) {
                    $mainIds[] = 'message_widget';
                } else {
                    array_splice($mainIds, (int) $timeslotIndex + 1, 0, ['message_widget']);
                }
            }
            $sanitized['main'] = array_values(array_unique($mainIds));
        }

        if (isset($sanitized['operations']) && is_array($sanitized['operations'])) {
            $sanitized['operations'] = array_values(array_filter(
                $sanitized['operations'],
                static function (string $widgetId): bool {
                    return $widgetId !== 'message_widget';
                }
            ));
        }

        if (isset($sanitizedWidths['main']) && is_array($sanitizedWidths['main']) && !isset($sanitizedWidths['main']['message_widget'])) {
            $sanitizedWidths['main']['message_widget'] = self::getDefaultWidthForWidget('message_widget');
        }

        $sanitized['widths'] = $sanitizedWidths;

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

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function getWidthOptions(): array {
        return [
            ['value' => '1/4', 'label' => '1 kolom'],
            ['value' => '1/5', 'label' => '2 kolommen'],
            ['value' => '3/4', 'label' => '3 kolommen'],
            ['value' => '1', 'label' => '1 - ' . __('hele breedte', 'bso-survival')],
        ];
    }

    public static function getDefaultWidthForWidget(string $widgetId): string {
        switch ($widgetId) {
            case 'timeslot_progress':
            case 'message_widget':
                return '1';
            default:
                return self::DEFAULT_WIDTH;
        }
    }

    public static function widthToSpan(string $width): int {
        return self::WIDTH_SPANS[$width] ?? self::WIDTH_SPANS[self::DEFAULT_WIDTH];
    }

    public static function widthToCssClass(string $width): string {
        $normalized = in_array($width, array_keys(self::WIDTH_SPANS), true) ? $width : self::DEFAULT_WIDTH;
        return 'bso-survival-dashboard__widget--width-' . str_replace('/', '-', $normalized);
    }

    /**
     * @param mixed $value
     */
    private function normalizeWidth($value, string $widgetId): string {
        $width = is_string($value) ? trim($value) : '';
        if ($width === '' || !array_key_exists($width, self::WIDTH_SPANS)) {
            return self::getDefaultWidthForWidget($widgetId);
        }

        return $width;
    }
}
