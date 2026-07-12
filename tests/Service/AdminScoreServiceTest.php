<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use BSO\Survival\Database\Repository\AuditLogRepositoryInterface;
use BSO\Survival\Database\Repository\ScoreEntryRepositoryInterface;
use BSO\Survival\Service\AdminScoreService;
use BSO\Survival\Service\AuditLogService;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\RankingService;
use BSO\Survival\Service\ScoreEntryService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AdminScoreServiceTest extends TestCase {
    /** @test */
    public function it_recalculates_positions_for_part_using_latest_score_entries(): void {
        $entries = new InMemoryAdminScoreRepository();
        $entries->latestNormalizedByPart = [
            11 => 54.5,
            12 => 40.0,
        ];

        $ranking = new FakeAdminRankingService();
        $auditRepo = new InMemoryAdminAuditLogRepository();
        $audit = new AuditLogService($auditRepo);

        $service = new AdminScoreService(
            new FakeAdminOverviewService(),
            new FakeAdminAssignmentRepository(),
            $entries,
            new FakeAdminScoreEntryService(),
            $ranking,
            $audit
        );

        $result = $service->recalculate([
            'event_id' => 7,
            'part_id' => 31,
            'changed_by' => 'planner',
        ]);

        $this->assertSame(7, $result['event_id']);
        $this->assertSame(31, $result['part_id']);
        $this->assertSame(2, $result['team_count']);
        $this->assertSame([11 => 1, 12 => 2], $result['positions']);
        $this->assertSame(31, $ranking->lastPartId);
        $this->assertSame([11 => 54.5, 12 => 40.0], $ranking->lastTeamNormalizedValues);
        $this->assertSame(1, count($auditRepo->rows));
        $this->assertSame('recalculated', $auditRepo->rows[0]->action);
    }

    /** @test */
    public function it_rejects_invalid_recalculate_payload(): void {
        $service = new AdminScoreService(
            new FakeAdminOverviewService(),
            new FakeAdminAssignmentRepository(),
            new InMemoryAdminScoreRepository(),
            new FakeAdminScoreEntryService(),
            new FakeAdminRankingService(),
            new AuditLogService(new InMemoryAdminAuditLogRepository())
        );

        $this->expectException(InvalidArgumentException::class);
        $service->recalculate([
            'event_id' => 0,
            'part_id' => 31,
        ]);
    }

    /** @test */
    public function it_initializes_missing_score_records_and_writes_a_valid_audit_entity_id(): void {
        $entries = new InMemoryAdminScoreRepositoryForInitialization();
        $assignments = new FakeAdminAssignmentRepositoryWithRows([
            (object) ['id' => 101],
            (object) ['id' => 102],
        ]);
        $auditRepo = new InMemoryAdminAuditLogRepository();

        $service = new AdminScoreService(
            new FakeAdminOverviewService(),
            $assignments,
            $entries,
            new FakeAdminScoreEntryService(),
            new FakeAdminRankingService(),
            new AuditLogService($auditRepo)
        );

        $result = $service->initializeForEvent(7, 'tester');

        $this->assertSame(2, $result['assignment_count']);
        $this->assertSame(2, $result['created_entries']);
        $this->assertSame(0, $result['skipped_existing']);
        $this->assertCount(2, $entries->insertedRows);
        $this->assertSame(101, (int) $entries->insertedRows[0]['assignment_id']);
        $this->assertSame(102, (int) $entries->insertedRows[1]['assignment_id']);

        $this->assertCount(1, $auditRepo->rows);
        $this->assertSame('event', $auditRepo->rows[0]->entity_type);
        $this->assertSame(7, (int) $auditRepo->rows[0]->entity_id);
        $this->assertSame('initialized', $auditRepo->rows[0]->action);
    }
}

class FakeAdminOverviewService extends DashboardOverviewService {
    public function __construct() {
    }

    public function getOverviewForEvent(int $eventId): array {
        return [
            'status' => [
                'is_read_only' => false,
                'is_published' => false,
            ],
        ];
    }
}

class FakeAdminAssignmentRepository implements AssignmentRepositoryInterface {
    public function findById(int $id) {
        return null;
    }

    public function findByEventId(int $eventId): array {
        return [];
    }
}

class FakeAdminAssignmentRepositoryWithRows implements AssignmentRepositoryInterface {
    /** @var array<int, object> */
    private $rows;

    /** @param array<int, object> $rows */
    public function __construct(array $rows) {
        $this->rows = $rows;
    }

    public function findById(int $id) {
        return null;
    }

    public function findByEventId(int $eventId): array {
        return $this->rows;
    }
}

class InMemoryAdminScoreRepository implements ScoreEntryRepositoryInterface {
    /** @var array<int, float> */
    public $latestRawByPart = [];

    /** @var array<int, float> */
    public $latestNormalizedByPart = [];

    public function findById(int $id) {
        return null;
    }

    public function insert(array $data) {
        return null;
    }

    public function updateById(int $id, array $data) {
        return null;
    }

    public function findLatestRawValuesByPart(int $eventId, int $partId): array {
        return $this->latestRawByPart;
    }

    public function findLatestNormalizedPointsByPart(int $eventId, int $partId): array {
        return $this->latestNormalizedByPart;
    }

    public function findAssignmentIdsWithEntries(array $assignmentIds): array {
        return [];
    }
}

class InMemoryAdminScoreRepositoryForInitialization extends InMemoryAdminScoreRepository {
    /** @var array<int, array<string, mixed>> */
    public $insertedRows = [];

    public function insert(array $data) {
        $this->insertedRows[] = $data;

        return (object) [
            'id' => count($this->insertedRows),
            'assignment_id' => $data['assignment_id'] ?? 0,
        ];
    }
}

class FakeAdminScoreEntryService extends ScoreEntryService {
    public function __construct() {
    }
}

class FakeAdminRankingService extends RankingService {
    /** @var int */
    public $lastPartId = 0;

    /** @var array<int, float|int> */
    public $lastTeamRawValues = [];

    /** @var array<int, float|int> */
    public $lastTeamNormalizedValues = [];

    public function __construct() {
    }

    public function refreshForPart(int $partId, array $teamRawValues): array {
        $this->lastPartId = $partId;
        $this->lastTeamRawValues = $teamRawValues;

        return [11 => 1, 12 => 2];
    }

    public function refreshForPartWithNormalized(int $partId, array $teamNormalizedPoints): array {
        $this->lastPartId = $partId;
        $this->lastTeamNormalizedValues = $teamNormalizedPoints;

        return [11 => 1, 12 => 2];
    }
}

class InMemoryAdminAuditLogRepository implements AuditLogRepositoryInterface {
    /** @var array<int, object> */
    public $rows = [];

    public function insert(array $data) {
        $row = (object) array_merge([
            'id' => count($this->rows) + 1,
        ], $data);

        $this->rows[] = $row;
        return $row;
    }
}
