<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\EventRepository;
use BSO\Survival\Database\Repository\PartRepository;
use BSO\Survival\Database\Repository\TeamRepository;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase {
    /**
     * @test
     */
    public function event_repository_queries_are_deterministic(): void {
        $wpdb = new FakeWpdb('wp_');

        $wpdb->setResults(
            "SELECT * FROM wp_bso_survival_events ORDER BY event_date ASC, id ASC",
            [
                (object) ['id' => 1, 'name' => 'E1'],
                (object) ['id' => 2, 'name' => 'E2'],
            ]
        );

        $wpdb->setRow(
            "SELECT * FROM wp_bso_survival_events WHERE id = 1 LIMIT 1",
            (object) ['id' => 1, 'name' => 'E1']
        );

        $wpdb->setResults(
            "SELECT * FROM wp_bso_survival_events WHERE status = 'actief' ORDER BY event_date ASC, id ASC",
            [
                (object) ['id' => 2, 'status' => 'actief'],
            ]
        );

        $repository = new EventRepository($wpdb);

        $all = $repository->findAll();
        $this->assertCount(2, $all);

        $single = $repository->findById(1);
        $this->assertSame(1, $single->id);

        $active = $repository->findByStatus('actief');
        $this->assertCount(1, $active);
        $this->assertSame('actief', $active[0]->status);
    }

    /**
     * @test
     */
    public function part_repository_can_fetch_and_count_by_event(): void {
        $wpdb = new FakeWpdb('wp_');

        $wpdb->setRow(
            "SELECT * FROM wp_bso_survival_parts WHERE id = 3 LIMIT 1",
            (object) ['id' => 3, 'name' => 'Kasteelspel']
        );

        $wpdb->setResults(
            "SELECT * FROM wp_bso_survival_parts WHERE event_id = 1 ORDER BY id ASC",
            [
                (object) ['id' => 1, 'name' => 'Kanovaren'],
                (object) ['id' => 2, 'name' => 'Touwbaan'],
            ]
        );

        $wpdb->setVar(
            "SELECT COUNT(*) FROM wp_bso_survival_parts WHERE event_id = 1",
            '12'
        );

        $repository = new PartRepository($wpdb);

        $single = $repository->findById(3);
        $this->assertSame('Kasteelspel', $single->name);

        $parts = $repository->findByEventId(1);
        $this->assertCount(2, $parts);

        $count = $repository->countByEventId(1);
        $this->assertSame(12, $count);
    }

    /**
     * @test
     */
    public function team_repository_can_fetch_and_count_by_event(): void {
        $wpdb = new FakeWpdb('wp_');

        $wpdb->setRow(
            "SELECT * FROM wp_bso_survival_teams WHERE id = 2 LIMIT 1",
            (object) ['id' => 2, 'name' => 'Team002']
        );

        $wpdb->setResults(
            "SELECT * FROM wp_bso_survival_teams WHERE event_id = 1 ORDER BY id ASC",
            [
                (object) ['id' => 1, 'name' => 'Team001'],
                (object) ['id' => 2, 'name' => 'Team002'],
                (object) ['id' => 3, 'name' => 'Team003'],
            ]
        );

        $wpdb->setVar(
            "SELECT COUNT(*) FROM wp_bso_survival_teams WHERE event_id = 1",
            '22'
        );

        $repository = new TeamRepository($wpdb);

        $single = $repository->findById(2);
        $this->assertSame('Team002', $single->name);

        $teams = $repository->findByEventId(1);
        $this->assertCount(3, $teams);

        $count = $repository->countByEventId(1);
        $this->assertSame(22, $count);
    }
}

class FakeWpdb {
    /** @var string */
    public $prefix;

    /** @var array<string, array<int, object>> */
    private $results = [];

    /** @var array<string, object> */
    private $rows = [];

    /** @var array<string, string> */
    private $vars = [];

    public function __construct(string $prefix) {
        $this->prefix = $prefix;
    }

    /**
     * @param array<int, object> $value
     */
    public function setResults(string $sql, array $value): void {
        $this->results[$sql] = $value;
    }

    /**
     * @param object $value
     */
    public function setRow(string $sql, $value): void {
        $this->rows[$sql] = $value;
    }

    public function setVar(string $sql, string $value): void {
        $this->vars[$sql] = $value;
    }

    /**
     * @param mixed ...$args
     */
    public function prepare(string $query, ...$args): string {
        $index = 0;
        return preg_replace_callback('/%[dsf]/', function ($matches) use (&$index, $args): string {
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
    }

    /**
     * @return array<int, object>
     */
    public function get_results(string $sql): array {
        return $this->results[$sql] ?? [];
    }

    /**
     * @return object|null
     */
    public function get_row(string $sql) {
        return $this->rows[$sql] ?? null;
    }

    /**
     * @return string|null
     */
    public function get_var(string $sql) {
        return $this->vars[$sql] ?? null;
    }
}
