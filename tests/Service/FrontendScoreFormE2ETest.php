<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Api\FrontendScoreRestController;
use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use BSO\Survival\Frontend\ScoreFormController;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\FrontendScoreSubmissionService;
use BSO\Survival\Service\ScoreEntryService;
use PHPUnit\Framework\TestCase;

class FrontendScoreFormE2ETest extends TestCase {
    protected function tearDown(): void {
        set_test_nonce_verification_result(1);
        reset_test_current_user_caps();
    }

    /** @test */
    public function it_executes_frontend_score_submit_flow_and_blocks_when_event_becomes_read_only(): void {
        set_test_current_user_caps(['read' => true]);

        $eventService = new E2EFakeEventService();
        $overviewService = new E2EFakeOverviewService();
        $assignmentRepository = new E2EFakeAssignmentRepository();

        $formController = new ScoreFormController($eventService, $overviewService, $assignmentRepository);
        $rendered = $formController->render([
            'event_id' => 7,
            'title' => 'E2E scoreformulier',
        ]);

        $this->assertStringContainsString('E2E scoreformulier', $rendered);
        $this->assertStringContainsString('bso-score-form', $rendered);
        $this->assertStringContainsString('Team Kompas - Hindernis A', $rendered);

        $submissionService = new FrontendScoreSubmissionService(
            $overviewService,
            $assignmentRepository,
            new E2EFakeScoreEntryService()
        );
        $restController = new FrontendScoreRestController($submissionService);

        $successResponse = $restController->submitScore(new E2EFakeScoreRequest([
            'score_nonce' => 'ok',
            'event_id' => 7,
            'assignment_id' => 100,
            'raw_value' => 62.25,
            'entered_by_role' => 'frontend_jury',
        ]));

        $this->assertTrue($successResponse['success']);
        $this->assertTrue($successResponse['data']['created']);
        $this->assertSame(1, $successResponse['data']['result']['score_entry_id']);
        $this->assertSame(62.25, $successResponse['data']['result']['normalized_points']);

        $overviewService->readOnly = true;

        $blockedResponse = $restController->submitScore(new E2EFakeScoreRequest([
            'score_nonce' => 'ok',
            'event_id' => 7,
            'assignment_id' => 100,
            'raw_value' => 40,
            'entered_by_role' => 'frontend_jury',
        ]));

        $this->assertSame('score_submit_blocked', $blockedResponse['error']['code']);
        $this->assertSame(409, $blockedResponse['error']['status']);

        $readOnlyRendered = $formController->render([
            'event_id' => 7,
            'title' => 'E2E scoreformulier',
        ]);

        $this->assertStringContainsString('Score-invoer is read-only geblokkeerd', $readOnlyRendered);
        $this->assertStringContainsString('disabled="disabled"', $readOnlyRendered);
    }
}

class E2EFakeEventService extends EventService {
    public function __construct() {
    }

    public function getEvent(int $id) {
        return (object) [
            'id' => $id,
            'name' => 'E2E Event',
            'status' => 'actief',
        ];
    }
}

class E2EFakeOverviewService extends DashboardOverviewService {
    /** @var bool */
    public $readOnly = false;

    public function __construct() {
    }

    public function getOverviewForEvent(int $eventId): array {
        return [
            'event' => (object) [
                'id' => $eventId,
                'name' => 'E2E Event',
                'status' => $this->readOnly ? 'afgesloten' : 'actief',
            ],
            'parts' => [(object) ['name' => 'Hindernis A']],
            'teams' => [(object) ['name' => 'Team Kompas']],
            'counts' => [
                'parts' => 1,
                'teams' => 1,
            ],
            'status' => [
                'event_status' => $this->readOnly ? 'afgesloten' : 'actief',
                'has_parts' => true,
                'has_teams' => true,
                'is_ready_for_planning' => true,
                'is_read_only' => $this->readOnly,
                'is_published' => false,
            ],
        ];
    }
}

class E2EFakeAssignmentRepository implements AssignmentRepositoryInterface {
    public function findById(int $id) {
        if ($id !== 100) {
            return null;
        }

        return (object) [
            'id' => 100,
            'event_id' => 7,
            'part_id' => 31,
            'team_id' => 21,
            'part_name' => 'Hindernis A',
            'team_name' => 'Team Kompas',
        ];
    }

    public function findByEventId(int $eventId): array {
        if ($eventId !== 7) {
            return [];
        }

        return [
            (object) [
                'id' => 100,
                'event_id' => 7,
                'part_id' => 31,
                'team_id' => 21,
                'part_name' => 'Hindernis A',
                'team_name' => 'Team Kompas',
            ],
        ];
    }
}

class E2EFakeScoreEntryService extends ScoreEntryService {
    public function __construct() {
    }

    public function submit(int $partId, int $assignmentId, $rawValue, $bonusPoints, string $enteredByRole, array $context = []) {
        return (object) [
            'id' => 1,
            'assignment_id' => $assignmentId,
            'raw_value' => (float) $rawValue,
            'bonus_points' => is_numeric($bonusPoints) ? (float) $bonusPoints : 0.0,
            'normalized_points' => (float) $rawValue,
            'status' => 'concept',
        ];
    }
}

class E2EFakeScoreRequest {
    /** @var array<string, mixed> */
    private $params;

    /**
     * @param array<string, mixed> $params
     */
    public function __construct(array $params) {
        $this->params = $params;
    }

    public function get_param(string $key) {
        return $this->params[$key] ?? null;
    }

    public function get_header(string $key): string {
        return '';
    }
}
