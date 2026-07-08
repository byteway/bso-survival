<?php

namespace BSO\Survival\Service;

class RegistrationConfirmationService {
    public const TEMPLATE_REGISTRATION_CONFIRMATION = 'registration_confirmation';

    /** @var EmailTemplateService */
    private $templates;

    /** @var EmailOutboxService */
    private $outbox;

    public function __construct(EmailTemplateService $templates, EmailOutboxService $outbox) {
        $this->templates = $templates;
        $this->outbox = $outbox;
    }

    /**
     * @param array<string, mixed> $registration
     */
    public function enqueueForRegistration(array $registration): bool {
        $eventId = (int) ($registration['event_id'] ?? 0);
        $teamId = (int) ($registration['team_id'] ?? 0);
        $recipient = strtolower(trim((string) ($registration['contact_email'] ?? '')));

        if ($eventId <= 0 || $teamId <= 0 || $recipient === '') {
            return false;
        }

        $context = [
            'vrijwilliger_naam' => (string) ($registration['contact_name'] ?? ''),
            'team_naam' => (string) ($registration['team_name'] ?? ''),
            'event_naam' => (string) ($registration['event_name'] ?? ''),
            'event_datum' => (string) ($registration['event_date'] ?? ''),
            'aantal_teamleden' => (string) (int) ($registration['team_members_count'] ?? 0),
            'inschrijf_id' => (string) ($registration['registration_id'] ?? ''),
        ];

        $rendered = $this->templates->render(
            EmailTemplateService::TEMPLATE_REGISTRATION_CONFIRMATION,
            $context
        );

        return $this->outbox->enqueue([
            'event_id' => $eventId,
            'recipient' => $recipient,
            'template_key' => EmailTemplateService::TEMPLATE_REGISTRATION_CONFIRMATION,
            'subject' => (string) ($rendered['subject'] ?? ''),
            'body' => (string) ($rendered['body'] ?? ''),
            'dedupe_key' => $this->buildDedupeKey($teamId, $recipient),
        ]);
    }

    public function buildDedupeKey(int $teamId, string $recipient): string {
        return sprintf(
            '%s:%d:%s',
            EmailTemplateService::TEMPLATE_REGISTRATION_CONFIRMATION,
            $teamId,
            strtolower(trim($recipient))
        );
    }

    /**
     * @return array<string, string>
     */
    public static function sampleContext(): array {
        return [
            'vrijwilliger_naam' => 'Voorbeeld Ouder',
            'team_naam' => 'Team Kompas',
            'event_naam' => 'BSO Survival Voorjaar',
            'event_datum' => '2026-07-08',
            'aantal_teamleden' => '6',
            'inschrijf_id' => '42',
        ];
    }
}
