<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\AssignmentRepositoryInterface;
use BSO\Survival\Service\InterimTeamScoreService;
use BSO\Survival\Service\PartConfirmationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PartConfirmationServiceTest extends TestCase {
    protected function setUp(): void {
        $ref = new \ReflectionClass(PartConfirmationService::class);
        $prop = $ref->getProperty('memoryState');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    /** @test */
    public function it_reports_ties_in_part_status(): void {
        $interim = $this->createInterim([
            'counts' => ['total' => 2, 'completed' => 2, 'pending' => 0],
            'rows' => [
                ['team_name' => 'Team A', 'raw_value' => 10, 'bonus_points' => 2, 'is_completed' => true],
                ['team_name' => 'Team B', 'raw_value' => 10, 'bonus_points' => 2, 'is_completed' => true],
            ],
        ]);

        $service = new PartConfirmationService($interim, $this->createAssignmentsRepo([]));

        $status = $service->getPartStatus(1, 9);

        $this->assertTrue($status['has_ties']);
        $this->assertFalse($status['can_confirm']);
        $this->assertCount(1, $status['tie_groups']);
        $this->assertSame(['Team A', 'Team B'], $status['tie_groups'][0]['teams']);
    }

    /** @test */
    public function it_blocks_confirmation_when_ties_exist(): void {
        $interim = $this->createInterim([
            'counts' => ['total' => 2, 'completed' => 2, 'pending' => 0],
            'rows' => [
                ['team_name' => 'Team A', 'raw_value' => 5, 'bonus_points' => 0, 'is_completed' => true],
                ['team_name' => 'Team B', 'raw_value' => 5, 'bonus_points' => 0, 'is_completed' => true],
            ],
        ]);

        $service = new PartConfirmationService($interim, $this->createAssignmentsRepo([(object) ['part_id' => 4]]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ties');
        $service->confirmPart(3, 4, 'referee', true);
    }

    /** @test */
    public function it_confirms_part_and_blocks_future_edits_for_that_part(): void {
        $interim = $this->createInterim([
            'counts' => ['total' => 2, 'completed' => 2, 'pending' => 0],
            'rows' => [
                ['team_name' => 'Team A', 'raw_value' => 7, 'bonus_points' => 0, 'is_completed' => true],
                ['team_name' => 'Team B', 'raw_value' => 9, 'bonus_points' => 0, 'is_completed' => true],
            ],
        ]);

        $service = new PartConfirmationService($interim, $this->createAssignmentsRepo([(object) ['part_id' => 4]]));

        $result = $service->confirmPart(2, 4, 'referee', true);

        $this->assertTrue($result['confirmed']);
        $this->assertTrue($service->isPartConfirmed(2, 4));
        $this->assertFalse((bool) ($result['finalization']['triggered'] ?? true));
    }

    /**
     * @param array<string, mixed> $overview
     */
    private function createInterim(array $overview): InterimTeamScoreService {
        $interim = $this->getMockBuilder(InterimTeamScoreService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPartOverview'])
            ->getMock();

        $interim->method('getPartOverview')->willReturn($overview);

        return $interim;
    }

    /**
     * @param array<int, object> $assignments
     */
    private function createAssignmentsRepo(array $assignments): AssignmentRepositoryInterface {
        return new class($assignments) implements AssignmentRepositoryInterface {
            /** @var array<int, object> */
            private $assignments;

            public function __construct(array $assignments) {
                $this->assignments = $assignments;
            }

            public function findById(int $id) {
                return null;
            }

            public function findByEventId(int $eventId): array {
                return $this->assignments;
            }
        };
    }
}
