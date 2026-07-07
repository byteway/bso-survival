<?php

namespace BSO\Survival\Tests\Support;

use PHPUnit\Framework\TestCase;

class GoldenDatasetTest extends TestCase {
    /**
     * @test
     */
    public function test_golden_dataset_has_expected_parts_and_teams(): void {
        $dataset = GoldenDataset::v1();

        $this->assertSame('1.0.0', $dataset['dataset_version']);
        $this->assertCount(12, $dataset['parts']);
        $this->assertCount(22, $dataset['teams']);
    }

    /**
     * @test
     */
    public function test_golden_dataset_part_names_are_exact_and_ordered(): void {
        $dataset = GoldenDataset::v1();
        $partNames = array_column($dataset['parts'], 'name');

        $this->assertSame([
            'Kanovaren',
            'Touwbaan',
            'Kasteelspel',
            'Kano Bungee',
            'Survivalbaan',
            'Vrachtauto / tokkelbaan',
            'Kano touwtrekken',
            'Water scheppen',
            'Water dragen',
            'Vlotten bouw',
            'Step-run',
            'Labyrint',
        ], $partNames);
    }

    /**
     * @test
     */
    public function test_golden_dataset_team_names_are_exact_and_ordered(): void {
        $dataset = GoldenDataset::v1();
        $teamNames = array_column($dataset['teams'], 'name');

        $expected = [];
        for ($i = 1; $i <= 22; $i++) {
            $expected[] = sprintf('Team%03d', $i);
        }

        $this->assertSame($expected, $teamNames);
    }
}
