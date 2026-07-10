<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\FrontendScoreSubmissionService;
use BSO\Survival\Service\ScoreEntryService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FrontendScoreSubmissionServiceTest extends TestCase {
    /** @test */
    public function it_submits_score_for_active_event_and_returns_updated_summary(): void {
        $overview = new FakeFrontendScoreOverviewService([
            7 => [
                'status' => [
                    'is_read_only' => false,
                    'is_published' => false,
                ],
                'counts' => [
                    'parts' => 4,
                    'teams' => 8,
                ],
            ],
        ]);

        $assignments = new FakeFrontendScoreAssignmentRepository([
            100 => (object) [
                'id' => 100,
                'event_id' => 7,
                'part_id' => 30,
            ],
        ]);

        $scores = new FakeFrontendScoreEntryService();

        $service = new FrontendScoreSubmissionService($overview, $assignments, $scores);
        $result = $service->submit([
            'event_id' => 7,
            'assignment_id' => 100,
            'raw_value' => 55.5,
            'entered_by_role' => 'jury',
        ]);

        $this->assertSame(1, $result['score_entry_id']);
        $this->assertSame(100, $result['assignment_id']);
        $this->assertSame(30, $result['part_id']);
        $this->assertSame(55.5, $result['raw_value']);
        $this->assertSame(55.5, $result['normalized_points']);
        $this->assertSame(4, $result['counts']['parts']);
        $this->assertSame(8, $result['counts']['teams']);
        $this->assertSame(1, count($scores->calls));
        $this->assertSame('frontend_score_form', $scores->calls[0]['context']['source']);
    }

    /** @test */
    public function it_blocks_submission_for_read_only_event_status(): void {
        $overview = new FakeFrontendScoreOverviewService([
            7 => [
                'status' => [
                    'is_read_only' => true,
                    'is_published' => false,
                ],
                'counts' => [
                    'parts' => 4,
                    'teams' => 8,
                ],
            ],
        ]);

        $assignments = new FakeFrontendScoreAssignmentRepository([
            100 => (object) [
                'id' => 100,
                'event_id' => 7,
                'part_id' => 30,
            ],
        ]);

        $service = new FrontendScoreSubmissionService($overview, $assignments, new FakeFrontendScoreEntryService());

        $this->expectException(RuntimeException::class);
        $service->submit([
            'event_id' => 7,
            'assignment_id' => 100,
            'raw_value' => 40,
            'entered_by_role' => 'jury',
        ]);
    }

    /** @test */
    public function it_rejects_assignment_that_does_not_belong_to_event(): void {
        $overview = new FakeFrontendScoreOverviewService([
            7 => [
                'status' => [
                    'is_read_only' => false,
                    'is_published' => false,
                ],
                'counts' => [
                    'parts' => 4,
                    'teams' => 8,
                ],
            ],
        ]);

        $assignments = new FakeFrontendScoreAssignmentRepository([
            100 => (object) [
                'id' => 100,
                'event_id' => 8,
                'part_id' => 30,
            ],
        ]);

        $service = new FrontendScoreSubmissionService($overview, $assignments, new FakeFrontendScoreEntryService());

        $this->expectException(InvalidArgumentException::class);
        $service->submit([
            'event_id' => 7,
            'assignment_id' => 100,
            'raw_value' => 40,
            'entered_by_role' => 'jury',
        ]);
    }
}

class FakeFrontendScoreOverviewService extends DashboardOverviewService {
    /** @var array<int, array<string, mixed>> */
    private $byEvent;

    /** @param array<int, array<string, mixed>> $byEvent */
    public function __construct(array $byEvent) {
        $this->byEvent = $byEvent;
    }

    public function getOverviewForEvent(int $eventId): array {
        return $this->byEvent[$eventId] ?? [
            'status' => [
                'is_read_only' => false,
                'is_published' => false,
            ],
            'counts' => [
                'parts' => 0,
                'teams' => 0,
            ],
        ];
    }
}

class FakeFrontendScoreAssignmentRepository implements AssignmentRepositoryInterface {
    /** @var array<int, object> */
    private $assignments;

    /** @param array<int, object> $assignments */
    public function __construct(array $assignments) {
        $this->assignments = $assignments;
    }

    public function findById(int $id) {
        return $this->assignments[$id] ?? null;
    }

    public function findByEventId(int $eventId): array {
        return array_values(array_filter($this->assignments, function ($assignment) use ($eventId) {
            return (int) ($assignment->event_id ?? 0) === $eventId;
        }));
    }
}

class FakeFrontendScoreEntryService extends ScoreEntryService {
    /** @var array<int, array<string, mixed>> */
    public $calls = [];

    public function __construct() {
    }

    public function submit(int $partId, int $assignmentId, $rawValue, $bonusPoints, string $enteredByRole, array $context = []) {
        $this->calls[] = [
            'part_id' => $partId,
            'assignment_id' => $assignmentId,
            'raw_value' => $rawValue,
            'bonus_points' => $bonusPoints,
            'entered_by_role' => $enteredByRole,
            'context' => $context,
        ];

        return (object) [
            'id' => 1,
            'raw_value' => (float) $rawValue,
            'bonus_points' => is_numeric($bonusPoints) ? (float) $bonusPoints : 0.0,
            'normalized_points' => (float) $rawValue,
            'status' => 'concept',
        ];
    }
}
