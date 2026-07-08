<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class FrontendScoreSubmissionService {
    /** @var DashboardOverviewService */
    private $overview;

    /** @var AssignmentRepositoryInterface */
    private $assignments;

    /** @var ScoreEntryService */
    private $scores;

    public function __construct(DashboardOverviewService $overview, AssignmentRepositoryInterface $assignments, ScoreEntryService $scores) {
        $this->overview = $overview;
        $this->assignments = $assignments;
        $this->scores = $scores;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submit(array $payload): array {
        $eventId = (int) ($payload['event_id'] ?? 0);
        $assignmentId = (int) ($payload['assignment_id'] ?? 0);
        $rawValue = $payload['raw_value'] ?? null;
        $enteredByRole = trim((string) ($payload['entered_by_role'] ?? 'frontend_jury'));

        if ($eventId <= 0) {
            throw new InvalidArgumentException('event_id must be a positive integer.');
        }

        if ($assignmentId <= 0) {
            throw new InvalidArgumentException('assignment_id must be a positive integer.');
        }

        if (!is_numeric($rawValue)) {
            throw new InvalidArgumentException('raw_value must be numeric.');
        }

        if ($enteredByRole === '') {
            $enteredByRole = 'frontend_jury';
        }

        $overview = $this->overview->getOverviewForEvent($eventId);
        $isReadOnly = !empty($overview['status']['is_read_only']);
        $isPublished = !empty($overview['status']['is_published']);

        if ($isReadOnly || $isPublished) {
            throw new RuntimeException('Score-invoer is geblokkeerd: event is read-only of gepubliceerd.');
        }

        $assignment = $this->assignments->findById($assignmentId);
        if ($assignment === null) {
            throw new InvalidArgumentException(sprintf('Assignment %d not found.', $assignmentId));
        }

        if ((int) ($assignment->event_id ?? 0) !== $eventId) {
            throw new InvalidArgumentException('assignment_id hoort niet bij dit event_id.');
        }

        $partId = (int) ($assignment->part_id ?? 0);
        if ($partId <= 0) {
            throw new RuntimeException('Assignment mist een geldig onderdeel.');
        }

        $stored = $this->scores->submit(
            $partId,
            $assignmentId,
            $rawValue,
            $enteredByRole,
            [
                'source' => 'frontend_score_form',
                'event_id' => $eventId,
            ]
        );

        $refreshedOverview = $this->overview->getOverviewForEvent($eventId);

        return [
            'score_entry_id' => (int) ($stored->id ?? 0),
            'assignment_id' => $assignmentId,
            'part_id' => $partId,
            'raw_value' => (float) ($stored->raw_value ?? 0),
            'normalized_points' => (float) ($stored->normalized_points ?? 0),
            'status' => (string) ($stored->status ?? 'concept'),
            'status_flags' => [
                'is_read_only' => !empty($refreshedOverview['status']['is_read_only']),
                'is_published' => !empty($refreshedOverview['status']['is_published']),
            ],
            'counts' => [
                'parts' => (int) ($refreshedOverview['counts']['parts'] ?? 0),
                'teams' => (int) ($refreshedOverview['counts']['teams'] ?? 0),
            ],
        ];
    }
}
