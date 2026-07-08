<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\ScoreEntryRepository;
use PHPUnit\Framework\TestCase;

class ScoreEntryRepositoryTest extends TestCase {
    /** @test */
    public function it_returns_latest_raw_values_grouped_by_team_for_part(): void {
        $wpdb = new FakeScoreEntryWpdb('wp_');
        $wpdb->nextResults = [
            (object) ['team_id' => 5, 'raw_value' => '62.5'],
            (object) ['team_id' => 8, 'raw_value' => '47.0'],
        ];

        $repository = new ScoreEntryRepository($wpdb);
        $values = $repository->findLatestRawValuesByPart(12, 31);

        $this->assertSame([5 => 62.5, 8 => 47.0], $values);
        $this->assertStringContainsString('a.event_id = 12', $wpdb->lastPreparedSql);
        $this->assertStringContainsString('a.part_id = 31', $wpdb->lastPreparedSql);
    }

    /** @test */
    public function it_returns_empty_values_for_invalid_ids(): void {
        $wpdb = new FakeScoreEntryWpdb('wp_');
        $repository = new ScoreEntryRepository($wpdb);

        $this->assertSame([], $repository->findLatestRawValuesByPart(0, 31));
        $this->assertSame([], $repository->findLatestRawValuesByPart(12, 0));
    }
}

class FakeScoreEntryWpdb {
    /** @var string */
    public $prefix;

    /** @var array<int, object> */
    public $nextResults = [];

    /** @var string */
    public $lastPreparedSql = '';

    public function __construct(string $prefix) {
        $this->prefix = $prefix;
    }

    /**
     * @param mixed ...$args
     */
    public function prepare(string $query, ...$args): string {
        $index = 0;
        $prepared = preg_replace_callback('/%[dsf]/', function ($matches) use (&$index, $args): string {
            $value = $args[$index++];
            if ($matches[0] === '%d') {
                return (string) (int) $value;
            }
            if ($matches[0] === '%f') {
                return (string) (float) $value;
            }

            $escaped = str_replace("'", "''", (string) $value);
            return "'{$escaped}'";
        }, $query) ?? $query;

        $this->lastPreparedSql = $prepared;
        return $prepared;
    }

    /**
     * @return array<int, object>
     */
    public function get_results(string $sql): array {
        return $this->nextResults;
    }

    public function get_row(string $sql) {
        return null;
    }

    public function insert(string $table, array $data) {
        return true;
    }

    public function update(string $table, array $data, array $where) {
        return 1;
    }
}
