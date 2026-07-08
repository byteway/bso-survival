<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Database\Repository\EmailOutboxRepositoryInterface;
use BSO\Survival\Database\Repository\EmailTemplateRepositoryInterface;
use BSO\Survival\Service\EmailOutboxService;
use BSO\Survival\Service\EmailTemplateService;
use BSO\Survival\Service\RegistrationConfirmationService;
use PHPUnit\Framework\TestCase;

class RegistrationConfirmationServiceTest extends TestCase {
    /** @test */
    public function it_queues_registration_confirmation_and_applies_placeholders(): void {
        $templateRepo = new RegistrationTemplateRepository();
        $outboxRepo = new RegistrationOutboxRepository();

        $templateService = new EmailTemplateService($templateRepo);
        $templateService->saveTemplate(
            EmailTemplateService::TEMPLATE_REGISTRATION_CONFIRMATION,
            'Bevestiging voor {team_naam}',
            '<p>Hallo {vrijwilliger_naam}, event {event_naam}, leden {aantal_teamleden}, id {inschrijf_id}</p>',
            'tester'
        );

        $service = new RegistrationConfirmationService(
            $templateService,
            new EmailOutboxService($outboxRepo)
        );

        $ok1 = $service->enqueueForRegistration([
            'event_id' => 7,
            'team_id' => 33,
            'team_name' => 'Team Kompas',
            'contact_name' => 'Ouder Voorbeeld',
            'contact_email' => 'ouder@example.test',
            'event_name' => 'BSO Survival',
            'event_date' => '2026-07-08',
            'team_members_count' => 5,
            'registration_id' => '33',
        ]);

        $ok2 = $service->enqueueForRegistration([
            'event_id' => 7,
            'team_id' => 33,
            'team_name' => 'Team Kompas',
            'contact_name' => 'Ouder Voorbeeld',
            'contact_email' => 'ouder@example.test',
            'event_name' => 'BSO Survival',
            'event_date' => '2026-07-08',
            'team_members_count' => 5,
            'registration_id' => '33',
        ]);

        $this->assertTrue($ok1);
        $this->assertTrue($ok2);
        $this->assertCount(1, $outboxRepo->rows);

        $row = array_values($outboxRepo->rows)[0];
        $this->assertSame('registration_confirmation', $row->template_key);
        $this->assertStringContainsString('Team Kompas', $row->subject_snapshot);
        $this->assertStringContainsString('Ouder Voorbeeld', $row->body_snapshot);
        $this->assertStringContainsString('leden 5', $row->body_snapshot);
    }
}

class RegistrationTemplateRepository implements EmailTemplateRepositoryInterface {
    /** @var array<string, object> */
    private $rows = [];

    public function findByKey(string $templateKey) {
        return $this->rows[$templateKey] ?? null;
    }

    public function upsertByKey(string $templateKey, array $data) {
        $existing = $this->rows[$templateKey] ?? (object) ['id' => count($this->rows) + 1, 'template_key' => $templateKey];
        $this->rows[$templateKey] = (object) array_merge((array) $existing, ['template_key' => $templateKey], $data);

        return $this->rows[$templateKey];
    }
}

class RegistrationOutboxRepository implements EmailOutboxRepositoryInterface {
    /** @var array<int, object> */
    public $rows = [];

    public function insert(array $data) {
        $id = count($this->rows) + 1;
        $row = (object) array_merge(['id' => $id], $data);
        $this->rows[$id] = $row;
        return $row;
    }

    public function findByDedupeKey(string $dedupeKey) {
        foreach ($this->rows as $row) {
            if ((string) ($row->dedupe_key ?? '') === $dedupeKey) {
                return $row;
            }
        }

        return null;
    }

    public function findDue(string $nowUtc, int $limit): array {
        return [];
    }

    public function markSent(int $id, string $sentAtUtc): bool {
        return true;
    }

    public function markForRetry(int $id, int $attemptCount, string $nextAttemptAtUtc, string $lastError): bool {
        return true;
    }

    public function markFailed(int $id, int $attemptCount, string $lastError): bool {
        return true;
    }

    public function findRecent(int $limit): array {
        return array_slice(array_values($this->rows), 0, max(1, $limit));
    }
}
