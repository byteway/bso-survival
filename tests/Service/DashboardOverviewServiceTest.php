<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Database\Repository\PartRepositoryInterface;
use BSO\Survival\Database\Repository\TeamRepositoryInterface;
use BSO\Survival\Service\DashboardOverviewService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartService;
use BSO\Survival\Service\TeamService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DashboardOverviewServiceTest extends TestCase {
    /**
     * @test
     */
    public function it_builds_a_dashboard_overview_for_an_event(): void {
        $service = $this->buildService();

        $overview = $service->getOverviewForEvent(1);

        $this->assertSame(1, $overview['event']->id);
        $this->assertSame('gepland', $overview['event']->status);
        $this->assertCount(2, $overview['parts']);
        $this->assertCount(3, $overview['teams']);
        $this->assertSame(12, $overview['counts']['parts']);
        $this->assertSame(22, $overview['counts']['teams']);
        $this->assertSame(22, $overview['counts']['registered_teams']);
        $this->assertSame(30, $overview['counts']['max_teams']);
        $this->assertTrue($overview['status']['has_parts']);
        $this->assertTrue($overview['status']['has_teams']);
        $this->assertTrue($overview['status']['is_ready_for_planning']);
        $this->assertFalse($overview['status']['is_registration_full']);
    }

    /**
     * @test
     */
    public function it_throws_for_invalid_event_ids(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->buildService()->getOverviewForEvent(0);
    }

    /**
     * @test
     */
    public function it_throws_when_the_event_is_missing(): void {
        $service = new DashboardOverviewService(
            new EventService(new MissingDashboardEventRepository()),
            new PartService(new DashboardFakePartRepository()),
            new TeamService(new DashboardFakeTeamRepository())
        );

        $this->expectException(InvalidArgumentException::class);
        $service->getOverviewForEvent(999);
    }

    /** @test */
    public function it_lists_only_upcoming_active_events_for_dashboard_selector(): void {
        $service = $this->buildService();

        $events = $service->listUpcomingActiveEvents(5);

        $this->assertCount(2, $events);
        $this->assertSame(3, (int) $events[0]->id);
        $this->assertSame(4, (int) $events[1]->id);
    }

    /** @test */
    public function it_resolves_first_upcoming_active_event_as_dashboard_default(): void {
        $service = $this->buildService();

        $defaultEventId = $service->resolveDefaultDashboardEventId();

        $this->assertSame(3, $defaultEventId);
    }

    private function buildService(): DashboardOverviewService {
        return new DashboardOverviewService(
            new EventService(new DashboardFakeEventRepository()),
            new PartService(new DashboardFakePartRepository()),
            new TeamService(new DashboardFakeTeamRepository())
        );
    }
}

class DashboardFakeEventRepository implements EventRepositoryInterface {
    /** @return array<int, object> */
    public function findAll(): array {
        return [
            (object) ['id' => 1, 'status' => 'gepland', 'event_date' => gmdate('Y-m-d')],
            (object) ['id' => 2, 'status' => 'verwijderd', 'event_date' => gmdate('Y-m-d')],
        ];
    }

    /** @return object|null */
    public function findById(int $id) {
        return $id === 1 ? (object) ['id' => 1, 'status' => 'gepland', 'meta_data' => json_encode(['max_teams' => 30])] : null;
    }

    /** @return array<int, object> */
    public function findByStatus(string $status): array {
        if ($status !== 'actief') {
            return [];
        }

        return [
            (object) ['id' => 7, 'status' => 'actief', 'event_date' => gmdate('Y-m-d', strtotime('-1 day'))],
            (object) ['id' => 4, 'status' => 'actief', 'event_date' => gmdate('Y-m-d', strtotime('+1 day'))],
            (object) ['id' => 3, 'status' => 'actief', 'event_date' => gmdate('Y-m-d')],
        ];
    }

    public function updateStatus(int $id, string $status): bool {
        return true;
    }
}

class MissingDashboardEventRepository implements EventRepositoryInterface {
    /** @return array<int, object> */
    public function findAll(): array {
        return [];
    }

    /** @return object|null */
    public function findById(int $id) {
        return null;
    }

    /** @return array<int, object> */
    public function findByStatus(string $status): array {
        return [];
    }

    public function updateStatus(int $id, string $status): bool {
        return false;
    }
}

class DashboardFakePartRepository implements PartRepositoryInterface {
    /** @return object|null */
    public function findById(int $id) {
        return (object) ['id' => 1, 'name' => 'Kanovaren'];
    }

    /** @return array<int, object> */
    public function findByEventId(int $eventId): array {
        return [
            (object) ['id' => 1, 'name' => 'Kanovaren'],
            (object) ['id' => 2, 'name' => 'Touwbaan'],
        ];
    }

    public function countByEventId(int $eventId): int {
        return 12;
    }
}

class DashboardFakeTeamRepository implements TeamRepositoryInterface {
    /** @return object|null */
    public function findById(int $id) {
        return (object) ['id' => 2, 'name' => 'Team002'];
    }

    /** @return array<int, object> */
    public function findByEventId(int $eventId): array {
        return [
            (object) ['id' => 1, 'name' => 'Team001'],
            (object) ['id' => 2, 'name' => 'Team002'],
            (object) ['id' => 3, 'name' => 'Team003'],
        ];
    }

    public function countByEventId(int $eventId): int {
        return 22;
    }

    public function findByEventIdAndName(int $eventId, string $name) {
        return null;
    }

    public function create(array $data) {
        return null;
    }

    public function updateById(int $id, array $data) {
        return null;
    }
}
