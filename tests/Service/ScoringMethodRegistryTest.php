<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Contracts\ScoringMethodInterface;
use BSO\Survival\Service\ScoringMethodRegistry;
use PHPUnit\Framework\TestCase;

class ScoringMethodRegistryTest extends TestCase {
    protected function setUp(): void {
        ScoringMethodRegistry::reset();
    }

    /**
     * @test
     */
    public function it_registers_and_fetches_methods(): void {
        $method = new FakeScoringMethod();
        ScoringMethodRegistry::register('fake', $method);

        $this->assertTrue(ScoringMethodRegistry::exists('fake'));
        $this->assertSame($method, ScoringMethodRegistry::get('fake'));
        $this->assertArrayHasKey('fake', ScoringMethodRegistry::all());
    }

    /**
     * @test
     */
    public function it_loads_defaults_and_allows_custom_hook_registration(): void {
        add_action('bso_survival_register_scoring_methods', function () {
            ScoringMethodRegistry::register('custom', new FakeScoringMethod());
        });

        ScoringMethodRegistry::initDefaults();

        $this->assertTrue(ScoringMethodRegistry::exists('time'));
        $this->assertTrue(ScoringMethodRegistry::exists('points'));
        $this->assertTrue(ScoringMethodRegistry::exists('distance'));
        $this->assertTrue(ScoringMethodRegistry::exists('custom'));
    }
}

class FakeScoringMethod implements ScoringMethodInterface {
    public function getId(): string {
        return 'fake';
    }

    public function getName(): string {
        return 'Fake';
    }

    public function getDescription(): string {
        return 'Fake method for tests';
    }

    public function validateRawValue($value, array $config = []): bool {
        return true;
    }

    public function normalizeToPoints($rawValue, array $config = []): float {
        return 50.0;
    }

    public function generatePositionProposal(array $teamScores): array {
        return [];
    }

    public function getFieldType(): string {
        return 'number';
    }

    public function getFieldUnit(): string {
        return 'points';
    }
}
