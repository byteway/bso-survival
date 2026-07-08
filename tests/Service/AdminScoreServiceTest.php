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
        $entries->latestRawByPart = [
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
        $this->assertSame([11 => 54.5, 12 => 40.0], $ranking->lastTeamRawValues);
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

class InMemoryAdminScoreRepository implements ScoreEntryRepositoryInterface {
    /** @var array<int, float> */
    public $latestRawByPart = [];

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

    public function __construct() {
    }

    public function refreshForPart(int $partId, array $teamRawValues): array {
        $this->lastPartId = $partId;
        $this->lastTeamRawValues = $teamRawValues;

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
