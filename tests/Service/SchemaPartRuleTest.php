<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Schema;
use PHPUnit\Framework\TestCase;

class SchemaPartRuleTest extends TestCase {
    /**
     * @test
     */
    public function part_rule_schema_contains_scoring_fields_for_registry_driven_methods(): void {
        $tables = Schema::tables();

        $this->assertArrayHasKey('part_rules', $tables);
        $this->assertArrayHasKey('columns', $tables['part_rules']);

        $columns = $tables['part_rules']['columns'];

        $this->assertArrayHasKey('scoring_mode', $columns);
        $this->assertArrayHasKey('scoring_config', $columns);
        $this->assertArrayHasKey('unit', $columns);
        $this->assertArrayHasKey('tiebreaker_mode', $columns);
    }
}
