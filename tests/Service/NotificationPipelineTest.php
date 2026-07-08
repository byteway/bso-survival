<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Contracts\MailerInterface;
use BSO\Survival\Database\Repository\EmailOutboxRepositoryInterface;
use BSO\Survival\Database\Repository\EmailTemplateRepositoryInterface;
use BSO\Survival\Service\EmailOutboxService;
use BSO\Survival\Service\EmailTemplateService;
use BSO\Survival\Service\OutboxProcessorService;
use BSO\Survival\Service\PublicationNotificationService;
use PHPUnit\Framework\TestCase;

class NotificationPipelineTest extends TestCase {
    /**
     * @test
     */
    public function publication_notifications_are_rendered_queued_and_sent(): void {
        $templateRepo = new InMemoryEmailTemplateRepository();
        $outboxRepo = new InMemoryEmailOutboxRepository();
        $mailer = new InMemoryMailer();

        $templateService = new EmailTemplateService($templateRepo);
        $templateService->saveTemplate(
            EmailTemplateService::TEMPLATE_PUBLICATION_RESULT,
            'Uitslag: {headline}',
            '<p>Event {event_id}</p><div>{top_3_html}</div>',
            'tester'
        );

        $outboxService = new EmailOutboxService($outboxRepo);
        $processor = new OutboxProcessorService($outboxService, $mailer);
        $service = new PublicationNotificationService($templateService, $outboxService, $processor);

        $summary = $service->sendPublicationNotifications(77, [
            'headline' => 'Finale uitslag',
            'published_at' => '2026-07-08T12:00:00+00:00',
            'top_3' => [
                ['rank' => 1, 'team_name' => 'Team Rood', 'points' => 98.5],
                ['rank' => 2, 'team_name' => 'Team Blauw', 'points' => 96.5],
                ['rank' => 3, 'team_name' => 'Team Groen', 'points' => 92.5],
            ],
            'recipients' => ['coach@example.test', 'invalid-email', 'Coach@example.test'],
        ], 'wedstrijdleiding');

        $this->assertSame(1, $summary['queued_count']);
        $this->assertSame(1, $summary['sent_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertSame(0, $summary['retry_count']);
        $this->assertSame(['coach@example.test'], $summary['sent_to']);

        $this->assertCount(1, $outboxRepo->records);
        $record = array_values($outboxRepo->records)[0];
        $this->assertSame('sent', $record->status);
        $this->assertStringContainsString('Finale uitslag', $record->subject_snapshot);
        $this->assertStringContainsString('Team Rood', $record->body_snapshot);

        $this->assertCount(1, $mailer->sentMessages);
        $this->assertSame('coach@example.test', $mailer->sentMessages[0]['recipient']);
    }

    /**
     * @test
     */
    public function outbox_processor_retries_and_marks_failed_after_max_attempts(): void {
        $outboxRepo = new InMemoryEmailOutboxRepository();
        $outboxService = new EmailOutboxService($outboxRepo);
        $mailer = new InMemoryMailer(['retry@example.test']);
        $processor = new OutboxProcessorService($outboxService, $mailer);

        $this->assertTrue($outboxService->enqueue([
            'event_id' => 9,
            'recipient' => 'retry@example.test',
            'template_key' => 'publication_result',
            'subject' => 'Subject',
            'body' => 'Body',
            'dedupe_key' => 'retry-key-1',
        ]));

        $attempt1 = $processor->processDue(10);
        $this->assertSame(1, $attempt1['retry']);
        $this->assertSame(0, $attempt1['failed']);

        $attempt2 = $processor->processDue(10);
        $this->assertSame(1, $attempt2['retry']);

        $attempt3 = $processor->processDue(10);
        $this->assertSame(1, $attempt3['retry']);

        $attempt4 = $processor->processDue(10);
        $this->assertSame(1, $attempt4['retry']);

        $attempt5 = $processor->processDue(10);
        $this->assertSame(1, $attempt5['failed']);
        $this->assertSame(['retry@example.test'], $attempt5['failed_to']);

        $record = array_values($outboxRepo->records)[0];
        $this->assertSame('failed', $record->status);
        $this->assertSame(5, (int) $record->attempt_count);
    }

    /**
     * @test
     */
    public function enqueue_rejects_invalid_payloads(): void {
        $outboxRepo = new InMemoryEmailOutboxRepository();
        $outboxService = new EmailOutboxService($outboxRepo);

        $this->assertFalse($outboxService->enqueue([
            'event_id' => 0,
            'recipient' => 'coach@example.test',
            'template_key' => 'publication_result',
            'subject' => 'Subject',
            'body' => 'Body',
            'dedupe_key' => 'invalid-1',
        ]));

        $this->assertFalse($outboxService->enqueue([
            'event_id' => 9,
            'recipient' => 'not-an-email',
            'template_key' => 'publication_result',
            'subject' => 'Subject',
            'body' => 'Body',
            'dedupe_key' => 'invalid-2',
        ]));

        $this->assertFalse($outboxService->enqueue([
            'event_id' => 9,
            'recipient' => 'coach@example.test',
            'template_key' => '',
            'subject' => 'Subject',
            'body' => 'Body',
            'dedupe_key' => 'invalid-3',
        ]));

        $this->assertCount(0, $outboxRepo->records);
    }

    /**
     * @test
     */
    public function publication_summary_counts_enqueue_failures_as_failed(): void {
        $templateRepo = new InMemoryEmailTemplateRepository();
        $outboxRepo = new InMemoryEmailOutboxRepository();
        $mailer = new InMemoryMailer();

        $templateService = new EmailTemplateService($templateRepo);
        $outboxService = new EmailOutboxService($outboxRepo);
        $processor = new OutboxProcessorService($outboxService, $mailer);
        $service = new PublicationNotificationService($templateService, $outboxService, $processor);

        $summary = $service->sendPublicationNotifications(88, [
            'headline' => 'Uitslag',
            'published_at' => '2026-07-08T12:00:00+00:00',
            'top_3' => [],
            'recipients' => ['coach@example.test', 'not-an-email'],
        ], 'wedstrijdleiding');

        $this->assertSame(1, $summary['queued_count']);
        $this->assertSame(1, $summary['sent_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertSame(['coach@example.test'], $summary['sent_to']);
    }

    /**
     * @test
     */
    public function outbox_processor_handles_mailer_exceptions_with_retry_then_fail(): void {
        $outboxRepo = new InMemoryEmailOutboxRepository();
        $outboxService = new EmailOutboxService($outboxRepo);
        $mailer = new InMemoryMailer([], ['explode@example.test']);
        $processor = new OutboxProcessorService($outboxService, $mailer);

        $this->assertTrue($outboxService->enqueue([
            'event_id' => 9,
            'recipient' => 'explode@example.test',
            'template_key' => 'publication_result',
            'subject' => 'Subject',
            'body' => 'Body',
            'dedupe_key' => 'explode-key-1',
        ]));

        $attempt1 = $processor->processDue(10);
        $this->assertSame(1, $attempt1['retry']);

        $attempt2 = $processor->processDue(10);
        $attempt3 = $processor->processDue(10);
        $attempt4 = $processor->processDue(10);
        $attempt5 = $processor->processDue(10);

        $this->assertSame(1, $attempt5['failed']);
        $this->assertSame(['explode@example.test'], $attempt5['failed_to']);

        $record = array_values($outboxRepo->records)[0];
        $this->assertSame('failed', $record->status);
        $this->assertStringContainsString('Simulated mailer exception', (string) $record->last_error);
    }
}

class InMemoryEmailTemplateRepository implements EmailTemplateRepositoryInterface {
    /** @var array<string, object> */
    public $templates = [];

    public function findByKey(string $templateKey) {
        return $this->templates[$templateKey] ?? null;
    }

    public function upsertByKey(string $templateKey, array $data) {
        $existing = $this->templates[$templateKey] ?? (object) ['id' => count($this->templates) + 1, 'template_key' => $templateKey];
        $this->templates[$templateKey] = (object) array_merge((array) $existing, ['template_key' => $templateKey], $data);

        return $this->templates[$templateKey];
    }
}

class InMemoryEmailOutboxRepository implements EmailOutboxRepositoryInterface {
    /** @var array<int, object> */
    public $records = [];

    public function insert(array $data) {
        $id = count($this->records) + 1;
        $row = (object) array_merge(['id' => $id], $data);
        $this->records[$id] = $row;

        return $row;
    }

    public function findByDedupeKey(string $dedupeKey) {
        foreach ($this->records as $record) {
            if ((string) ($record->dedupe_key ?? '') === $dedupeKey) {
                return $record;
            }
        }

        return null;
    }

    public function findDue(string $nowUtc, int $limit): array {
        $due = [];
        foreach ($this->records as $record) {
            $status = (string) ($record->status ?? '');
            if ($status === 'queued' || $status === 'retry') {
                $due[] = $record;
            }
        }

        return array_slice($due, 0, $limit);
    }

    public function markSent(int $id, string $sentAtUtc): bool {
        if (!isset($this->records[$id])) {
            return false;
        }

        $this->records[$id]->status = 'sent';
        $this->records[$id]->sent_at = $sentAtUtc;
        $this->records[$id]->updated_at = $sentAtUtc;

        return true;
    }

    public function markForRetry(int $id, int $attemptCount, string $nextAttemptAtUtc, string $lastError): bool {
        if (!isset($this->records[$id])) {
            return false;
        }

        $this->records[$id]->status = 'retry';
        $this->records[$id]->attempt_count = $attemptCount;
        $this->records[$id]->next_attempt_at = $nextAttemptAtUtc;
        $this->records[$id]->last_error = $lastError;

        return true;
    }

    public function markFailed(int $id, int $attemptCount, string $lastError): bool {
        if (!isset($this->records[$id])) {
            return false;
        }

        $this->records[$id]->status = 'failed';
        $this->records[$id]->attempt_count = $attemptCount;
        $this->records[$id]->last_error = $lastError;

        return true;
    }

    public function findRecent(int $limit): array {
        $records = array_values($this->records);
        usort($records, static function ($a, $b): int {
            return ((int) ($b->id ?? 0)) <=> ((int) ($a->id ?? 0));
        });

        return array_slice($records, 0, max(1, $limit));
    }
}

class InMemoryMailer implements MailerInterface {
    /** @var array<int, string> */
    private $alwaysFailRecipients;

    /** @var array<int, string> */
    private $throwRecipients;

    /** @var array<int, array<string, string>> */
    public $sentMessages = [];

    /**
     * @param array<int, string> $alwaysFailRecipients
     * @param array<int, string> $throwRecipients
     */
    public function __construct(array $alwaysFailRecipients = [], array $throwRecipients = []) {
        $this->alwaysFailRecipients = $alwaysFailRecipients;
        $this->throwRecipients = $throwRecipients;
    }

    public function send(string $recipient, string $subject, string $body, $headers = [], array $attachments = []): bool {
        if (in_array($recipient, $this->throwRecipients, true)) {
            throw new \RuntimeException('Simulated mailer exception for ' . $recipient);
        }

        if (in_array($recipient, $this->alwaysFailRecipients, true)) {
            return false;
        }

        $this->sentMessages[] = [
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
        ];

        return true;
    }
}
