<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Service\ScoringMethods\DistanceScoringMethod;
use BSO\Survival\Service\ScoringMethods\PointsScoringMethod;
use BSO\Survival\Service\ScoringMethods\TimeScoringMethod;
use PHPUnit\Framework\TestCase;

class ScoringMethodsTest extends TestCase {
    /**
     * @test
     */
    public function time_method_validates_normalizes_and_ranks(): void {
        $method = new TimeScoringMethod();

        $this->assertTrue($method->validateRawValue(120));
        $this->assertFalse($method->validateRawValue(-1));
        $this->assertSame(90.0, $method->normalizeToPoints(120, ['max_time' => 1200]));

        $positions = $method->generatePositionProposal([
            10 => 80,
            11 => 95,
            12 => 60,
        ]);

        $this->assertSame([11 => 1, 10 => 2, 12 => 3], $positions);
    }

    /**
     * @test
     */
    public function points_method_validates_normalizes_and_ranks(): void {
        $method = new PointsScoringMethod();

        $this->assertTrue($method->validateRawValue(40));
        $this->assertFalse($method->validateRawValue(-5));
        $this->assertSame(50.0, $method->normalizeToPoints(40, ['max_points' => 80]));

        $positions = $method->generatePositionProposal([
            1 => 20,
            2 => 45,
            3 => 35,
        ]);

        $this->assertSame([2 => 1, 3 => 2, 1 => 3], $positions);
    }

    /**
     * @test
     */
    public function distance_method_validates_normalizes_and_ranks(): void {
        $method = new DistanceScoringMethod();

        $this->assertTrue($method->validateRawValue(250));
        $this->assertFalse($method->validateRawValue(-3));
        $this->assertSame(50.0, $method->normalizeToPoints(250, ['max_distance' => 500]));

        $positions = $method->generatePositionProposal([
            7 => 33,
            8 => 66,
            9 => 11,
        ]);

        $this->assertSame([8 => 1, 7 => 2, 9 => 3], $positions);
    }
}
