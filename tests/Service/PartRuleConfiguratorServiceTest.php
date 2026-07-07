<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use BSO\Survival\Service\PartRuleConfiguratorService;
use BSO\Survival\Service\ScoringMethodRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PartRuleConfiguratorServiceTest extends TestCase {
    protected function setUp(): void {
        ScoringMethodRegistry::reset();
        ScoringMethodRegistry::initDefaults();
    }

    /**
     * @test
     */
    public function it_saves_rule_with_unit_and_sanitized_config_for_selected_mode(): void {
        $repo = new InMemoryPartRuleRepository();
        $service = new PartRuleConfiguratorService($repo);

        $saved = $service->configure(10, 'time', [
            'max_time' => 900,
            'max_points' => 777,
            'normalization_curve' => 'linear',
        ]);

        $this->assertTrue($saved);

        $rule = $repo->findByPartId(10);
        $this->assertNotNull($rule);
        $this->assertSame('time', $rule->scoring_mode);
        $this->assertSame('seconden', $rule->unit);

        $config = json_decode((string) $rule->scoring_config, true);
        $this->assertSame(900, $config['max_time']);
        $this->assertArrayNotHasKey('max_points', $config);
    }

    /**
     * @test
     */
    public function it_throws_for_unknown_scoring_mode(): void {
        $repo = new InMemoryPartRuleRepository();
        $service = new PartRuleConfiguratorService($repo);

        $this->expectException(InvalidArgumentException::class);
        $service->configure(10, 'unknown_mode', []);
    }

    /**
     * @test
     */
    public function it_sanitizes_tiebreaker_curve_and_numeric_minimums(): void {
        $repo = new InMemoryPartRuleRepository();
        $service = new PartRuleConfiguratorService($repo);

        $service->configure(11, 'points', [
            'max_points' => 0,
            'normalization_curve' => 'weird-curve',
        ], 'unsupported_tiebreaker');

        $rule = $repo->findByPartId(11);
        $this->assertNotNull($rule);
        $this->assertSame('manual_referee', $rule->tiebreaker_mode);

        $config = json_decode((string) $rule->scoring_config, true);
        $this->assertSame('linear', $config['normalization_curve']);
        $this->assertSame(1, $config['max_points']);
    }
}

class InMemoryPartRuleRepository implements PartRuleRepositoryInterface {
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
