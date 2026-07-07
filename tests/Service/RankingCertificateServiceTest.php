<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\CertificateRepositoryInterface;
use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use BSO\Survival\Service\CertificateService;
use BSO\Survival\Service\PartRuleConfiguratorService;
use BSO\Survival\Service\RankingService;
use BSO\Survival\Service\ScoreComputationService;
use BSO\Survival\Service\ScoringMethodRegistry;
use PHPUnit\Framework\TestCase;

class RankingCertificateServiceTest extends TestCase {
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
    public function ranking_service_emits_hooks_and_returns_positions(): void {
        $beforeCalls = [];
        $afterCalls = [];

        add_action('bso_survival_before_ranking_refresh', function ($partId, $teamRawValues) use (&$beforeCalls): void {
            $beforeCalls[] = [$partId, $teamRawValues];
        }, 10, 2);

        add_action('bso_survival_ranking_updated', function ($partId, $positions, $teamRawValues) use (&$afterCalls): void {
            $afterCalls[] = [$partId, $positions, $teamRawValues];
        }, 10, 3);

        $rules = new InMemoryRankingPartRuleRepository();
        $configurator = new PartRuleConfiguratorService($rules);
        $scoring = new ScoreComputationService($rules);
        $service = new RankingService($scoring);

        $configurator->configure(41, 'points', [
            'max_points' => 100,
            'normalization_curve' => 'linear',
        ]);

        $positions = $service->refreshForPart(41, [11 => 80, 22 => 30]);

        $this->assertSame(1, count($beforeCalls));
        $this->assertSame(1, count($afterCalls));
        $this->assertSame(41, $beforeCalls[0][0]);
        $this->assertSame([11 => 80, 22 => 30], $beforeCalls[0][1]);
        $this->assertSame(41, $afterCalls[0][0]);
        $this->assertSame([11 => 1, 22 => 2], $positions);
        $this->assertSame([11 => 1, 22 => 2], $afterCalls[0][1]);
    }

    /**
     * @test
     */
    public function certificate_service_emits_hooks_and_persists_certificate(): void {
        $beforeCalls = [];
        $afterCalls = [];

        add_action('bso_survival_before_certificate_generated', function ($payload, $meta) use (&$beforeCalls): void {
            $beforeCalls[] = [$payload, $meta];
        }, 10, 2);

        add_action('bso_survival_certificate_generated', function ($certificateId, $eventId, $teamId, $certificate, $meta) use (&$afterCalls): void {
            $afterCalls[] = [$certificateId, $eventId, $teamId, $certificate, $meta];
        }, 10, 5);

        $service = new CertificateService(new InMemoryCertificateRepository());
        $certificate = $service->generate(52, 99, '/tmp/certificates/team-99.pdf', ['format' => 'pdf']);

        $this->assertSame(1, count($beforeCalls));
        $this->assertSame(1, count($afterCalls));
        $this->assertSame(1, $certificate->id);
        $this->assertSame(52, $certificate->event_id);
        $this->assertSame(99, $certificate->team_id);
        $this->assertSame('/tmp/certificates/team-99.pdf', $certificate->file_path);
        $this->assertSame(1, $afterCalls[0][0]);
        $this->assertSame(52, $afterCalls[0][1]);
        $this->assertSame(99, $afterCalls[0][2]);
        $this->assertSame('/tmp/certificates/team-99.pdf', $beforeCalls[0][0]['file_path']);
        $this->assertSame('pdf', $beforeCalls[0][1]['format']);
    }
}

class InMemoryRankingPartRuleRepository implements PartRuleRepositoryInterface {
    /** @var array<int, object> */
    private $rules = [];

    /** @return object|null */
    public function findByPartId(int $partId) {
        return $this->rules[$partId] ?? null;
    }

    /** @return array<int, object> */
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

class InMemoryCertificateRepository implements CertificateRepositoryInterface {
    /** @var array<int, object> */
    private $items = [];

    /** @param array<string, mixed> $data @return object|null */
    public function insert(array $data) {
        $id = count($this->items) + 1;
        $record = (object) array_merge(['id' => $id], $data);
        $this->items[$id] = $record;

        return $record;
    }
}
