<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use BSO\Survival\Database\Repository\ScoreEntryRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class AdminScoreService {
    /** @var DashboardOverviewService */
    private $overview;

    /** @var AssignmentRepositoryInterface */
    private $assignments;

    /** @var ScoreEntryRepositoryInterface */
    private $entries;

    /** @var ScoreEntryService */
    private $scoreEntryService;

    /** @var RankingService */
    private $ranking;

    /** @var AuditLogService */
    private $audit;

    public function __construct(
        DashboardOverviewService $overview,
        AssignmentRepositoryInterface $assignments,
        ScoreEntryRepositoryInterface $entries,
        ScoreEntryService $scoreEntryService,
        RankingService $ranking,
        AuditLogService $audit
    ) {
        $this->overview = $overview;
        $this->assignments = $assignments;
        $this->entries = $entries;
        $this->scoreEntryService = $scoreEntryService;
        $this->ranking = $ranking;
        $this->audit = $audit;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array {
        $eventId = (int) ($payload['event_id'] ?? 0);
        $assignmentId = (int) ($payload['assignment_id'] ?? 0);
        $rawValue = $payload['raw_value'] ?? null;
        $changedBy = trim((string) ($payload['changed_by'] ?? 'admin'));
        $enteredByRole = trim((string) ($payload['entered_by_role'] ?? 'admin'));
        $meta = $this->normalizeMeta($payload['meta'] ?? null);

        $assignment = $this->validateWritableAssignment($eventId, $assignmentId, $rawValue);
        $partId = (int) ($assignment->part_id ?? 0);
        $teamId = (int) ($assignment->team_id ?? 0);

        $stored = $this->scoreEntryService->submit($partId, $assignmentId, $rawValue, $enteredByRole, [
            'source' => (string) ($meta['source'] ?? 'admin_score'),
            'event_id' => $eventId,
            'labels' => $meta['labels'] ?? [],
            'trace_id' => (string) ($meta['trace_id'] ?? ''),
        ]);

        $positions = $this->ranking->refreshForPart($partId, [
            $teamId => (float) $rawValue,
        ]);

        $this->audit->log(
            $eventId,
            'score_entry',
            (int) ($stored->id ?? 0),
            'created',
            null,
            [
                'assignment_id' => $assignmentId,
                'raw_value' => (float) $rawValue,
                'normalized_points' => (float) ($stored->normalized_points ?? 0),
                'meta' => $meta,
            ],
            $changedBy
        );

        return [
            'score_entry_id' => (int) ($stored->id ?? 0),
            'assignment_id' => $assignmentId,
            'event_id' => $eventId,
            'part_id' => $partId,
            'normalized_points' => (float) ($stored->normalized_points ?? 0),
            'positions' => $positions,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(int $scoreEntryId, array $payload): array {
        if ($scoreEntryId <= 0) {
            throw new InvalidArgumentException('score_entry_id must be a positive integer.');
        }

        $eventId = (int) ($payload['event_id'] ?? 0);
        $rawValue = $payload['raw_value'] ?? null;
        $changedBy = trim((string) ($payload['changed_by'] ?? 'admin'));
        $enteredByRole = trim((string) ($payload['entered_by_role'] ?? 'admin'));
        $meta = $this->normalizeMeta($payload['meta'] ?? null);

        $existing = $this->entries->findById($scoreEntryId);
        if ($existing === null) {
            throw new InvalidArgumentException(sprintf('score entry %d not found.', $scoreEntryId));
        }

        $assignmentId = (int) ($existing->assignment_id ?? 0);
        $assignment = $this->validateWritableAssignment($eventId, $assignmentId, $rawValue);
        $partId = (int) ($assignment->part_id ?? 0);
        $teamId = (int) ($assignment->team_id ?? 0);

        $updated = $this->scoreEntryService->updateEntry(
            $scoreEntryId,
            $partId,
            $assignmentId,
            $rawValue,
            $enteredByRole,
            [
                'source' => (string) ($meta['source'] ?? 'admin_score_edit'),
                'event_id' => $eventId,
                'labels' => $meta['labels'] ?? [],
                'trace_id' => (string) ($meta['trace_id'] ?? ''),
            ]
        );

        $positions = $this->ranking->refreshForPart($partId, [
            $teamId => (float) $rawValue,
        ]);

        $this->audit->log(
            $eventId,
            'score_entry',
            $scoreEntryId,
            'updated',
            [
                'raw_value' => (float) ($existing->raw_value ?? 0),
                'normalized_points' => (float) ($existing->normalized_points ?? 0),
            ],
            [
                'raw_value' => (float) $rawValue,
                'normalized_points' => (float) ($updated->normalized_points ?? 0),
                'meta' => $meta,
            ],
            $changedBy
        );

        return [
            'score_entry_id' => $scoreEntryId,
            'assignment_id' => $assignmentId,
            'event_id' => $eventId,
            'part_id' => $partId,
            'normalized_points' => (float) ($updated->normalized_points ?? 0),
            'positions' => $positions,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function recalculate(array $payload): array {
        $eventId = (int) ($payload['event_id'] ?? 0);
        $partId = (int) ($payload['part_id'] ?? 0);
        $changedBy = trim((string) ($payload['changed_by'] ?? 'admin'));

        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($partId <= 0) {
            throw new InvalidArgumentException('part_id must be a positive integer.');
        }

        $teamRawValues = $this->entries->findLatestRawValuesByPart($eventId, $partId);
        $positions = $this->ranking->refreshForPart($partId, $teamRawValues);

        $this->audit->log(
            $eventId,
            'ranking',
            $partId,
            'recalculated',
            null,
            [
                'team_count' => count($teamRawValues),
            ],
            $changedBy === '' ? 'admin' : $changedBy
        );

        return [
            'event_id' => $eventId,
            'part_id' => $partId,
            'team_count' => count($teamRawValues),
            'positions' => $positions,
        ];
    }

    /**
     * @param mixed $rawValue
     * @return object
     */
    private function validateWritableAssignment(int $eventId, int $assignmentId, $rawValue) {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($assignmentId <= 0) {
            throw new InvalidArgumentException('assignment_id must be a positive integer.');
        }

        if (!is_numeric($rawValue)) {
            throw new InvalidArgumentException('raw_value must be numeric.');
        }

        $overview = $this->overview->getOverviewForEvent($eventId);
        if (!empty($overview['status']['is_read_only']) || !empty($overview['status']['is_published'])) {
            throw new RuntimeException('Score-invoer is geblokkeerd omdat event read-only of gepubliceerd is.');
        }

        $assignment = $this->assignments->findById($assignmentId);
        if ($assignment === null) {
            throw new InvalidArgumentException(sprintf('assignment %d not found.', $assignmentId));
        }

        if ((int) ($assignment->event_id ?? 0) !== $eventId) {
            throw new InvalidArgumentException('assignment_id hoort niet bij dit event_id.');
        }

        if ((int) ($assignment->part_id ?? 0) <= 0 || (int) ($assignment->team_id ?? 0) <= 0) {
            throw new RuntimeException('assignment bevat ongeldige part/team koppeling.');
        }

        return $assignment;
    }

    /**
     * @param mixed $meta
     * @return array<string, mixed>
     */
    private function normalizeMeta($meta): array {
        if ($meta === null) {
            return [];
        }

        if (!is_array($meta)) {
            throw new InvalidArgumentException('meta moet een object zijn.');
        }

        $allowedKeys = ['source', 'labels', 'trace_id'];
        $unknown = array_diff(array_keys($meta), $allowedKeys);
        if ($unknown !== []) {
            throw new InvalidArgumentException('meta bevat onbekende velden: ' . implode(',', $unknown));
        }

        $normalized = [];

        if (array_key_exists('source', $meta)) {
            $source = trim((string) $meta['source']);
            if ($source === '') {
                throw new InvalidArgumentException('meta.source moet een niet-lege string zijn.');
            }

            $normalized['source'] = $source;
        }

        if (array_key_exists('trace_id', $meta)) {
            $traceId = trim((string) $meta['trace_id']);
            if ($traceId === '') {
                throw new InvalidArgumentException('meta.trace_id moet een niet-lege string zijn.');
            }

            $normalized['trace_id'] = $traceId;
        }

        if (array_key_exists('labels', $meta)) {
            if (!is_array($meta['labels'])) {
                throw new InvalidArgumentException('meta.labels moet een array van strings zijn.');
            }

            $labels = [];
            foreach ($meta['labels'] as $label) {
                $value = trim((string) $label);
                if ($value === '') {
                    throw new InvalidArgumentException('meta.labels mag geen lege waarden bevatten.');
                }

                $labels[] = $value;
            }

            $normalized['labels'] = array_values(array_unique($labels));
        }

        return $normalized;
    }
}
