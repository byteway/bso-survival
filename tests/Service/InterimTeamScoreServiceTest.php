<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use BSO\Survival\Service\InterimTeamScoreService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class InterimTeamScoreServiceTest extends TestCase {
    /** @test */
    public function it_ranks_higher_raw_first_for_manual_referee(): void {
        $service = new InterimTeamScoreService(new InMemoryInterimRuleRepository());

        $rows = [
            $this->row('TeamA', 1001, 123.0, 0.0, false, 'referee'),
            $this->row('TeamB', 1002, 580.0, 0.0, false, 'referee'),
        ];

        $ranked = $this->invokeRankPartRows($service, $rows, (object) [
            'tiebreaker_mode' => 'manual_referee',
            'scoring_mode' => 'time',
        ]);

        $this->assertSame('TeamB', (string) $ranked[0]['team_name']);
        $this->assertSame(1, (int) $ranked[0]['provisional_position']);
        $this->assertSame(20, (int) $ranked[0]['interim_score']);

        $this->assertSame('TeamA', (string) $ranked[1]['team_name']);
        $this->assertSame(2, (int) $ranked[1]['provisional_position']);
        $this->assertSame(10, (int) $ranked[1]['interim_score']);
    }

    /** @test */
    public function it_ranks_lower_raw_first_for_lower_raw_wins(): void {
        $service = new InterimTeamScoreService(new InMemoryInterimRuleRepository());

        $rows = [
            $this->row('TeamA', 1001, 123.0, 0.0, false, 'referee'),
            $this->row('TeamB', 1002, 580.0, 0.0, false, 'referee'),
        ];

        $ranked = $this->invokeRankPartRows($service, $rows, (object) [
            'tiebreaker_mode' => 'lower_raw_wins',
            'scoring_mode' => 'time',
        ]);

        $this->assertSame('TeamA', (string) $ranked[0]['team_name']);
        $this->assertSame(1, (int) $ranked[0]['provisional_position']);
    }

    /** @test */
    public function it_uses_bonus_as_tie_breaker_when_raw_values_are_equal(): void {
        $service = new InterimTeamScoreService(new InMemoryInterimRuleRepository());

        $rows = [
            $this->row('TeamA', 1001, 500.0, 0.0, false, 'referee'),
            $this->row('TeamB', 1002, 500.0, 2.0, false, 'referee'),
            $this->row('TeamC', 1003, 450.0, 0.0, false, 'referee'),
        ];

        $ranked = $this->invokeRankPartRows($service, $rows, (object) [
            'tiebreaker_mode' => 'higher_raw_wins',
            'scoring_mode' => 'points',
        ]);

        $this->assertSame('TeamB', (string) $ranked[0]['team_name']);
        $this->assertSame(1, (int) $ranked[0]['provisional_position']);
        $this->assertSame(30, (int) $ranked[0]['interim_score']);

        $this->assertSame('TeamA', (string) $ranked[1]['team_name']);
        $this->assertSame(2, (int) $ranked[1]['provisional_position']);
        $this->assertSame(20, (int) $ranked[1]['interim_score']);

        $this->assertSame('TeamC', (string) $ranked[2]['team_name']);
        $this->assertSame(3, (int) $ranked[2]['provisional_position']);
        $this->assertSame(10, (int) $ranked[2]['interim_score']);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function invokeRankPartRows(InterimTeamScoreService $service, array $rows, $rule): array {
        $method = new ReflectionMethod(InterimTeamScoreService::class, 'rankPartRows');
        $method->setAccessible(true);

        /** @var array<int, array<string, mixed>> $ranked */
        $ranked = $method->invoke($service, $rows, $rule);
        return $ranked;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $teamName, int $assignmentId, float $rawValue, float $bonusPoints, bool $jokerApplied, string $enteredByRole): array {
        return [
            'assignment_id' => $assignmentId,
            'team_id' => $assignmentId,
            'team_name' => $teamName,
            'score_entry_id' => $assignmentId,
            'raw_value' => $rawValue,
            'bonus_points' => $bonusPoints,
            'joker_applied' => $jokerApplied,
            'entered_by_role' => $enteredByRole,
        ];
    }
}

class InMemoryInterimRuleRepository implements PartRuleRepositoryInterface {
    public function findByPartId(int $partId) {
        return null;
    }

    public function findByEventId(int $eventId): array {
        return [];
    }

    public function upsertForPart(int $partId, string $scoringMode, string $unit, string $tiebreakerMode, string $scoringConfig): bool {
        return true;
    }
}
