<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\AuditLogRepositoryInterface;
use BSO\Survival\Service\AuditLogService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AuditLogServiceTest extends TestCase {
    protected function setUp(): void {
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
    public function it_emits_hooks_and_persists_an_audit_log_entry(): void {
        $beforeCalls = [];
        $afterCalls = [];

        add_action('bso_survival_before_audit_log_write', function ($payload, $context) use (&$beforeCalls): void {
            $beforeCalls[] = [$payload, $context];
        }, 10, 2);

        add_action('bso_survival_audit_log_written', function ($auditLogId, $payload, $stored, $context) use (&$afterCalls): void {
            $afterCalls[] = [$auditLogId, $payload, $stored, $context];
        }, 10, 4);

        $service = new AuditLogService(new InMemoryAuditLogRepository());
        $stored = $service->log(7, 'event', 12, 'updated', ['status' => 'concept'], ['status' => 'actief'], 'admin', ['source' => 'unit-test']);

        $this->assertSame(1, count($beforeCalls));
        $this->assertSame(1, count($afterCalls));
        $this->assertSame(1, $stored->id);
        $this->assertSame(7, $stored->event_id);
        $this->assertSame('event', $stored->entity_type);
        $this->assertSame(12, $stored->entity_id);
        $this->assertSame('updated', $stored->action);
        $this->assertSame('{"status":"concept"}', $stored->old_value);
        $this->assertSame('{"status":"actief"}', $stored->new_value);
        $this->assertSame('admin', $stored->changed_by);
        $this->assertSame('unit-test', $beforeCalls[0][1]['source']);
        $this->assertSame(1, $afterCalls[0][0]);
        $this->assertSame('updated', $afterCalls[0][1]['action']);
    }

    /**
     * @test
     */
    public function it_emits_failure_hook_when_persistence_fails(): void {
        $failureCalls = [];

        add_action('bso_survival_audit_log_failed', function ($payload, $context, $exception) use (&$failureCalls): void {
            $failureCalls[] = [$payload, $context, $exception];
        }, 10, 3);

        $service = new AuditLogService(new FailingAuditLogRepository());

        $this->expectException(RuntimeException::class);
        try {
            $service->log(null, 'team', 5, 'deleted', ['name' => 'Team 1'], null, 'system');
        } finally {
            $this->assertSame(1, count($failureCalls));
            $this->assertSame('deleted', $failureCalls[0][0]['action']);
            $this->assertNull($failureCalls[0][2]);
        }
    }

    /**
     * @test
     */
    public function it_validates_required_fields(): void {
        $service = new AuditLogService(new InMemoryAuditLogRepository());

        $this->expectException(InvalidArgumentException::class);
        $service->log(0, '', 0, '', null, null, '');
    }
}

class InMemoryAuditLogRepository implements AuditLogRepositoryInterface {
    /** @var array<int, object> */
    private $rows = [];

    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data) {
        $id = count($this->rows) + 1;
        $row = (object) array_merge(['id' => $id], $data);
        $this->rows[$id] = $row;

        return $row;
    }
}

class FailingAuditLogRepository implements AuditLogRepositoryInterface {
    /**
     * @param array<string, mixed> $data
     * @return object|null
     */
    public function insert(array $data) {
        return null;
    }
}
