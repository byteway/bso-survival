<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use BSO\Survival\Service\PartRuleConfiguratorService;
use BSO\Survival\Service\ScoreComputationService;
use BSO\Survival\Service\ScoringMethodRegistry;
use PHPUnit\Framework\TestCase;

class PartRuleScoringFlowTest extends TestCase {
    protected function setUp(): void {
        ScoringMethodRegistry::reset();
        ScoringMethodRegistry::initDefaults();
    }

    /**
     * @test
     */
    public function it_applies_saved_part_rule_config_in_score_normalization_and_position_proposal(): void {
        $repo = new FlowInMemoryPartRuleRepository();
        $configurator = new PartRuleConfiguratorService($repo);
        $scoring = new ScoreComputationService($repo);

        $configurator->configure(20, 'distance', [
            'max_distance' => 1000,
            'normalization_curve' => 'linear',
        ]);

        $normalized = $scoring->normalizeRawValueForPart(20, 400);
        $this->assertSame(40.0, $normalized);

        $positions = $scoring->positionProposalForPart(20, [
            101 => 300,
            102 => 600,
            103 => 100,
        ]);

        $this->assertSame([102 => 1, 101 => 2, 103 => 3], $positions);
    }
}

class FlowInMemoryPartRuleRepository implements PartRuleRepositoryInterface {
    /** @var array<int, object> */
    private $rules = [];

    /**
     * @return object|null
     */
    public function findByPartId(int $partId) {
        return $this->rules[$partId] ?? null;
    }

    /**
     * @return array<int, object>
     */
    public function findByEventId(int $eventId): array {
        return [];
    }

    public function upsertForPart(int $partId, string $scoringMode, string $unit, string $tiebreakerMode, string $scoringConfig): bool {
        $this->rules[$partId] = (object) [
            'part_id' => $partId,
            'scoring_mode' => $scoringMode,
            'unit' => $unit,
            'tiebreaker_mode' => $tiebreakerMode,
            'scoring_config' => $scoringConfig,
        ];

        return true;
    }
}
