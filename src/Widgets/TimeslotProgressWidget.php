<?php

namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class TimeslotProgressWidget implements DashboardWidgetInterface {
    /** @var callable|null */
    private $nowProvider;

    public function __construct(?callable $nowProvider = null) {
        $this->nowProvider = $nowProvider;
    }

    public function getId(): string {
        return 'timeslot_progress';
    }

    public function getTitle(): string {
        return 'Tijdslot voortgang';
    }

    public function getPriority(): int {
        return 10;
    }

    public function getCapabilities(): array {
        return ['read'];
    }

    public function getData(array $overview, array $filters = []): array {
        $eventId = (int) ($overview['event']->id ?? 0);
        $timeslots = $this->loadTimeslots($eventId);

        if ($timeslots === []) {
            return [
                'total_progress' => 0,
                'current_slot_progress' => 0,
                'current_slot_label' => __('Geen tijdsloten', 'bso-survival'),
                'segments' => [],
            ];
        }

        $nowUtc = $this->resolveNowTimestampUtc();
        $eventStart = (int) ($timeslots[0]['start_ts'] ?? 0);
        $eventEnd = (int) ($timeslots[count($timeslots) - 1]['end_ts'] ?? 0);
        $eventDuration = max(1, $eventEnd - $eventStart);

        $elapsedTotal = max(0, min($eventDuration, $nowUtc - $eventStart));
        $totalProgress = (int) round(($elapsedTotal / $eventDuration) * 100);

        $currentSlotProgress = 0;
        $currentSlotLabel = __('Geen actief tijdslot', 'bso-survival');
        $segments = [];

        foreach ($timeslots as $index => $slot) {
            $startTs = (int) ($slot['start_ts'] ?? 0);
            $endTs = (int) ($slot['end_ts'] ?? 0);
            $duration = max(1, $endTs - $startTs);

            $state = 'pending';
            if ($nowUtc >= $endTs) {
                $state = 'completed';
            } elseif ($nowUtc >= $startTs && $nowUtc < $endTs) {
                $state = 'active';
                $currentSlotProgress = (int) round((max(0, min($duration, $nowUtc - $startTs)) / $duration) * 100);
                $currentSlotLabel = sprintf(__('Tijdslot %d actief', 'bso-survival'), $index + 1);
            }

            $segments[] = [
                'number' => $index + 1,
                'time_range' => sprintf('%s - %s', (string) ($slot['start_label'] ?? ''), (string) ($slot['end_label'] ?? '')),
                'start_label' => (string) ($slot['start_label'] ?? ''),
                'end_label' => (string) ($slot['end_label'] ?? ''),
                'state' => $state,
            ];
        }

        if ($nowUtc >= $eventEnd) {
            $currentSlotProgress = 100;
            $currentSlotLabel = __('Alle tijdsloten afgerond', 'bso-survival');
        }

        return [
            'total_progress' => $totalProgress,
            'current_slot_progress' => $currentSlotProgress,
            'current_slot_label' => $currentSlotLabel,
            'segments' => $segments,
        ];
    }

    public function render(array $context): string {
        $data = is_array($context['data'] ?? null) ? $context['data'] : [];
        $totalProgress = (int) ($data['total_progress'] ?? 0);
        $currentSlotProgress = (int) ($data['current_slot_progress'] ?? 0);
        $currentSlotLabel = (string) ($data['current_slot_label'] ?? __('Geen actief tijdslot', 'bso-survival'));
        $segments = is_array($data['segments'] ?? null) ? $data['segments'] : [];

        $segmentsHtml = '';
        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }

            $state = (string) ($segment['state'] ?? 'pending');
            $number = (int) ($segment['number'] ?? 0);
            $range = (string) ($segment['time_range'] ?? '');
            $startLabel = (string) ($segment['start_label'] ?? '');
            $endLabel = (string) ($segment['end_label'] ?? '');
            $segmentsHtml .= '<figure class="bso-timeslot-segment bso-timeslot-segment--' . esc_attr($state) . '" title="' . esc_attr(trim($range)) . '">' .
                '<span class="bso-timeslot-segment__start">' . esc_html($startLabel) . '</span>' .
                '<span class="bso-timeslot-segment__number">' . esc_html((string) $number) . '</span>' .
                '<span class="bso-timeslot-segment__end">' . esc_html($endLabel) . '</span>' .
                '</figure>';
        }

        if ($segmentsHtml === '') {
            $segmentsHtml = '<figure class="bso-timeslot-segment bso-timeslot-segment--empty"><span class="bso-timeslot-segment__start"></span><span class="bso-timeslot-segment__number">-</span><span class="bso-timeslot-segment__end"></span></figure>';
        }

        return '<article class="bso-widget bso-widget-timeslot"><h3>' . esc_html($this->getTitle()) . '</h3>' .
            '<div class="bso-widget-timeslot__metrics">' .
            '<div class="bso-widget-timeslot__metric"><strong>' . esc_html((string) $totalProgress) . '%</strong><span>' . esc_html(__('Totale voortgang event', 'bso-survival')) . '</span></div>' .
            '<div class="bso-widget-timeslot__metric"><strong>' . esc_html((string) $currentSlotProgress) . '%</strong><span>' . esc_html($currentSlotLabel) . '</span></div>' .
            '</div>' .
            '<div class="bso-widget-timeslot__figure-title">' . esc_html(__('Tijdsloten', 'bso-survival')) . '</div>' .
            '<div class="bso-widget-timeslot__bar" aria-label="' . esc_attr(__('Tijdslot voortgangsbalk', 'bso-survival')) . '">' . $segmentsHtml . '</div>' .
            '</article>';
    }

    public function getScriptDependencies(): array {
        return ['bso-survival-dashboard-widgets'];
    }

    public function getStyleDependencies(): array {
        return ['bso-survival-dashboard-widgets'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadTimeslots(int $eventId): array {
        if ($eventId <= 0) {
            return [];
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return [];
        }

        $table = $wpdb->prefix . 'bso_survival_timeslots';
        $sql = $wpdb->prepare(
            "SELECT id, start_at, end_at FROM {$table} WHERE event_id = %d ORDER BY start_at ASC, id ASC",
            $eventId
        );

        $results = $wpdb->get_results($sql) ?: [];
        $rows = [];
        foreach ($results as $result) {
            $startTs = $this->toUtcTimestamp((string) ($result->start_at ?? ''));
            $endTs = $this->toUtcTimestamp((string) ($result->end_at ?? ''));
            if ($startTs <= 0 || $endTs <= $startTs) {
                continue;
            }

            $rows[] = [
                'id' => (int) ($result->id ?? 0),
                'start_ts' => $startTs,
                'end_ts' => $endTs,
                'start_label' => gmdate('H:i', $startTs),
                'end_label' => gmdate('H:i', $endTs),
            ];
        }

        return $rows;
    }

    private function resolveNowTimestampUtc(): int {
        if ($this->nowProvider !== null) {
            return (int) call_user_func($this->nowProvider);
        }

        return time();
    }

    private function toUtcTimestamp(string $value): int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if ($date === false) {
            return 0;
        }

        return $date->getTimestamp();
    }
}
