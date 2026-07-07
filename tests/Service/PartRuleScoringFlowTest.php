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

        global $wp_filters;
        $wp_filters = [];
    }

    protected function tearDown(): void {
        global $wp_filters;
        $wp_filters = [];
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

    /**
     * @test
     */
    public function it_applies_normalization_and_position_filters(): void {
        add_filter('bso_survival_score_normalized_points', function ($normalized, $rawValue, $partId) {
            return $normalized + 5;
        }, 10, 3);

        add_filter('bso_survival_position_proposal', function ($positions, $partId) {
            return [
                103 => 1,
                101 => 2,
                102 => 3,
            ];
        }, 10, 3);

        $repo = new FlowInMemoryPartRuleRepository();
        $configurator = new PartRuleConfiguratorService($repo);
        $scoring = new ScoreComputationService($repo);

        $configurator->configure(21, 'points', [
            'max_points' => 100,
            'normalization_curve' => 'linear',
        ]);

        $normalized = $scoring->normalizeRawValueForPart(21, 40);
        $this->assertSame(45.0, $normalized);

        $positions = $scoring->positionProposalForPart(21, [
            101 => 30,
            102 => 60,
            103 => 10,
        ]);

        $this->assertSame([103 => 1, 101 => 2, 102 => 3], $positions);
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
