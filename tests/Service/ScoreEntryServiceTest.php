<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use BSO\Survival\Database\Repository\ScoreEntryRepositoryInterface;
use BSO\Survival\Service\PartRuleConfiguratorService;
use BSO\Survival\Service\ScoreComputationService;
use BSO\Survival\Service\ScoreEntryService;
use BSO\Survival\Service\ScoringMethodRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ScoreEntryServiceTest extends TestCase {
    protected function setUp(): void {
        ScoringMethodRegistry::reset();
        ScoringMethodRegistry::initDefaults();

        global $wp_actions;
        $wp_actions = [];
    }

    protected function tearDown(): void {
        global $wp_actions;
        $wp_actions = [];
    }

    /**
     * @test
     */
    public function it_emits_hooks_and_persists_a_valid_score_entry(): void {
        $beforeCalls = [];
        $recordedCalls = [];

        add_action('bso_survival_before_score_validation', function ($entry) use (&$beforeCalls): void {
            $beforeCalls[] = $entry;
        }, 10, 1);

        add_action('bso_survival_score_recorded', function ($scoreEntryId, $assignmentId, $rawValue, $entry) use (&$recordedCalls): void {
            $recordedCalls[] = [$scoreEntryId, $assignmentId, $rawValue, $entry];
        }, 10, 4);

        $rules = new InMemoryScoreEntryPartRuleRepository();
        $configurator = new PartRuleConfiguratorService($rules);
        $scoring = new ScoreComputationService($rules);
        $entries = new InMemoryScoreEntryRepository();
        $service = new ScoreEntryService($entries, $scoring);

        $configurator->configure(31, 'points', [
            'max_points' => 100,
            'normalization_curve' => 'linear',
        ]);

        $stored = $service->submit(31, 301, 44, 'referee', ['source' => 'admin']);

        $this->assertSame(1, count($beforeCalls));
        $this->assertSame(1, count($recordedCalls));
        $this->assertSame(1, $stored->id);
        $this->assertSame(301, $stored->assignment_id);
        $this->assertSame(44, $stored->raw_value);
        $this->assertSame(44.0, (float) $stored->normalized_points);
        $this->assertSame(1, $recordedCalls[0][0]);
        $this->assertSame(301, $recordedCalls[0][1]);
        $this->assertSame(44, $recordedCalls[0][2]);
        $this->assertSame(31, $beforeCalls[0]['part_id']);
        $this->assertSame(301, $beforeCalls[0]['assignment_id']);
        $this->assertSame(44, $beforeCalls[0]['raw_value']);
        $this->assertSame('referee', $beforeCalls[0]['entered_by_role']);
    }

    /**
     * @test
     */
    public function it_validates_input_before_recording(): void {
        $rules = new InMemoryScoreEntryPartRuleRepository();
        $configurator = new PartRuleConfiguratorService($rules);
        $scoring = new ScoreComputationService($rules);
        $entries = new InMemoryScoreEntryRepository();
        $service = new ScoreEntryService($entries, $scoring);

        $configurator->configure(32, 'points', [
            'max_points' => 100,
            'normalization_curve' => 'linear',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $service->submit(32, 302, 'not-a-number', 'referee');
    }
}

class InMemoryScoreEntryPartRuleRepository implements PartRuleRepositoryInterface {
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

class InMemoryScoreEntryRepository implements ScoreEntryRepositoryInterface {
    /** @var array<int, object> */
    private $entries = [];

    public function findById(int $id) {
        return $this->entries[$id] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data) {
        $id = count($this->entries) + 1;
        $entry = (object) array_merge(['id' => $id], $data);
        $this->entries[$id] = $entry;

        return $entry;
    }

    public function updateById(int $id, array $data) {
        if (!isset($this->entries[$id])) {
            return null;
        }

        $this->entries[$id] = (object) array_merge((array) $this->entries[$id], $data);
        return $this->entries[$id];
    }
}