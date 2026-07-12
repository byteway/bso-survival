<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\EmailTemplateRepositoryInterface;
use InvalidArgumentException;

class EmailTemplateService {
    public const TEMPLATE_PUBLICATION_RESULT = 'publication_result';
    public const TEMPLATE_REGISTRATION_CONFIRMATION = 'registration_confirmation';

    /** @var EmailTemplateRepositoryInterface */
    private $templates;

    public function __construct(EmailTemplateRepositoryInterface $templates) {
        $this->templates = $templates;
    }

    /** @return object */
    public function getTemplate(string $templateKey) {
        $template = $this->templates->findByKey($templateKey);
        if ($template !== null && (int) ($template->is_active ?? 1) === 1) {
            return $template;
        }

        return (object) $this->defaultTemplate($templateKey);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{subject:string, body:string}
     */
    public function render(string $templateKey, array $context): array {
        $template = $this->getTemplate($templateKey);

        return [
            'subject' => $this->replaceTokens((string) ($template->subject ?? ''), $context),
            'body' => $this->replaceTokens((string) ($template->html_body ?? ''), $context),
        ];
    }

    public function saveTemplate(string $templateKey, string $subject, string $htmlBody, string $updatedBy): bool {
        $this->validateTemplatePlaceholders($templateKey, $subject . "\n" . $htmlBody);

        $now = gmdate('Y-m-d H:i:s');
        $stored = $this->templates->upsertByKey($templateKey, [
            'subject' => $subject,
            'html_body' => $htmlBody,
            'is_active' => 1,
            'updated_by' => $updatedBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $stored !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultTemplate(string $templateKey): array {
        if ($templateKey === self::TEMPLATE_REGISTRATION_CONFIRMATION) {
            return [
                'template_key' => self::TEMPLATE_REGISTRATION_CONFIRMATION,
                'subject' => 'Inschrijving ontvangen voor {team_naam}',
                'html_body' => '<h2>Bedankt voor je inschrijving, {vrijwilliger_naam}</h2><p>Je team <strong>{team_naam}</strong> is ingeschreven voor {event_naam} op {event_datum}.</p><p>Aantal teamleden: {aantal_teamleden}</p><p>Registratie-ID: {inschrijf_id}</p>',
                'is_active' => 1,
            ];
        }

        if ($templateKey !== self::TEMPLATE_PUBLICATION_RESULT) {
            return [
                'template_key' => $templateKey,
                'subject' => 'BSO Survival notificatie',
                'html_body' => '<p>Er is een nieuwe notificatie beschikbaar.</p>',
                'is_active' => 1,
            ];
        }

        return [
            'template_key' => self::TEMPLATE_PUBLICATION_RESULT,
            'subject' => '{headline}',
            'html_body' => '<h2>{headline}</h2><p>De eindstand van event #{event_id} is gepubliceerd op {published_at}.</p><h3>Top 3</h3>{top_3_html}<h3>Volledige eindstand</h3>{final_standings_html}<p>{appreciation_message}</p>',
            'is_active' => 1,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function replaceTokens(string $input, array $context): string {
        $output = $input;
        foreach ($context as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $output = str_replace('{' . $key . '}', (string) $value, $output);
        }

        return $output;
    }

    private function validateTemplatePlaceholders(string $templateKey, string $content): void {
        if (!preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $content, $matches)) {
            return;
        }

        $allowed = $this->allowedPlaceholders($templateKey);
        $unknown = [];

        foreach ($matches[1] as $token) {
            if (!in_array($token, $allowed, true)) {
                $unknown[] = $token;
            }
        }

        $unknown = array_values(array_unique($unknown));
        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Onbekende placeholders: ' . implode(', ', $unknown)
            );
        }
    }

    /**
     * @return array<int, string>
     */
    public function allowedPlaceholders(string $templateKey): array {
        if ($templateKey === self::TEMPLATE_REGISTRATION_CONFIRMATION) {
            return [
                'vrijwilliger_naam',
                'team_naam',
                'event_naam',
                'event_datum',
                'aantal_teamleden',
                'inschrijf_id',
            ];
        }

        if ($templateKey === self::TEMPLATE_PUBLICATION_RESULT) {
            return ['headline', 'event_id', 'published_at', 'top_3_html', 'final_standings_html', 'appreciation_message'];
        }

        return [];
    }
}
