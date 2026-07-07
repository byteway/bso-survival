<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Service\EventService;
use BSO\Survival\Service\PartService;
use BSO\Survival\Service\TeamService;
use BSO\Survival\Database\Repository\EventRepositoryInterface;
use BSO\Survival\Database\Repository\PartRepositoryInterface;
use BSO\Survival\Database\Repository\TeamRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ServiceLayerTest extends TestCase {
    /**
     * @test
     */
    public function event_service_lists_active_events_and_validates_ids(): void {
        $repository = new FakeEventRepository();
        $service = new EventService($repository);

        $all = $service->listEvents();
        $this->assertCount(2, $all);

        $active = $service->listActiveEvents();
        $this->assertCount(1, $active);
        $this->assertSame('actief', $active[0]->status);

        $this->assertSame(1, $service->getEvent(1)->id);

        $this->expectException(InvalidArgumentException::class);
        $service->getEvent(0);
    }

    /**
     * @test
     */
    public function part_service_lists_and_counts_parts_for_event(): void {
        $repository = new FakePartRepository();
        $service = new PartService($repository);

        $parts = $service->listPartsForEvent(1);
        $this->assertCount(2, $parts);
        $this->assertSame('Kanovaren', $parts[0]->name);

        $this->assertSame(12, $service->countPartsForEvent(1));
        $this->assertSame('Kasteelspel', $service->getPart(3)->name);

        $this->expectException(InvalidArgumentException::class);
        $service->countPartsForEvent(-1);
    }

    /**
     * @test
     */
    public function team_service_lists_and_counts_teams_for_event(): void {
        $repository = new FakeTeamRepository();
        $service = new TeamService($repository);

        $teams = $service->listTeamsForEvent(1);
        $this->assertCount(3, $teams);
        $this->assertSame('Team001', $teams[0]->name);

        $this->assertSame(22, $service->countTeamsForEvent(1));
        $this->assertSame('Team002', $service->getTeam(2)->name);

        $this->expectException(InvalidArgumentException::class);
        $service->getTeam(0);
    }
}

class FakeEventRepository implements EventRepositoryInterface {
    /** @return array<int, object> */
    public function findAll(): array {
        return [
            (object) ['id' => 1, 'status' => 'concept'],
            (object) ['id' => 2, 'status' => 'actief'],
        ];
    }

    /** @return array<int, object> */
    public function findByStatus(string $status): array {
        return array_values(array_filter($this->findAll(), static function ($event) use ($status): bool {
            return $event->status === $status;
        }));
    }

    /** @return object|null */
    public function findById(int $id) {
        foreach ($this->findAll() as $event) {
            if ($event->id === $id) {
                return $event;
            }
        }

        return null;
    }
}

class FakePartRepository implements PartRepositoryInterface {
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

    /** @return object|null */
    public function findById(int $id) {
        return (object) ['id' => 3, 'name' => 'Kasteelspel'];
    }
}

class FakeTeamRepository implements TeamRepositoryInterface {
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

    /** @return object|null */
    public function findById(int $id) {
        return (object) ['id' => 2, 'name' => 'Team002'];
    }
}
