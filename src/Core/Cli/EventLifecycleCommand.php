<?php

namespace BSO\Survival\Core\Cli;

use BSO\Survival\Database\Repository\AuditLogRepository;
use BSO\Survival\Database\Repository\CertificateRepository;
use BSO\Survival\Database\Repository\EmailOutboxRepository;
use BSO\Survival\Database\Repository\EmailTemplateRepository;
use BSO\Survival\Database\Repository\EventPublicationRepository;
use BSO\Survival\Database\Repository\EventRepository;
use BSO\Survival\Service\AuditLogService;
use BSO\Survival\Service\CertificateService;
use BSO\Survival\Service\EmailOutboxService;
use BSO\Survival\Service\EmailTemplateService;
use BSO\Survival\Service\EventCloseoutService;
use BSO\Survival\Service\EventPublicationService;
use BSO\Survival\Service\EventService;
use BSO\Survival\Service\OutboxProcessorService;
use BSO\Survival\Service\PublicationNotificationService;
use BSO\Survival\Service\WpMailer;
use InvalidArgumentException;

class EventLifecycleCommand {
    /**
     * Execute event lifecycle actions for closeout/publication.
     *
     * ## OPTIONS
     *
     * --phase=<closeout|publish>
     * : Lifecycle phase to execute.
     *
     * --event_id=<id>
     * : Target event id.
     *
     * [--changed_by=<name>]
     * : Operator name, default: wp-cli.
     *
     * [--certificates=<json>]
     * : JSON array payload for closeout certificates.
     *
     * [--publication=<json>]
     * : JSON object payload for publication details.
     */
    public function __invoke(array $args, array $assocArgs): void {
        $phase = isset($assocArgs['phase']) ? (string) $assocArgs['phase'] : '';
        $eventId = isset($assocArgs['event_id']) ? (int) $assocArgs['event_id'] : 0;
        $changedBy = isset($assocArgs['changed_by']) ? (string) $assocArgs['changed_by'] : 'wp-cli';

        if (!in_array($phase, ['closeout', 'publish'], true)) {
            \WP_CLI::error('Parameter --phase moet closeout of publish zijn.');
            return;
        }

        if ($eventId <= 0) {
            \WP_CLI::error('Parameter --event_id moet een positief getal zijn.');
            return;
        }

        $service = $this->buildCloseoutService();

        try {
            if ($phase === 'closeout') {
                $certificates = $this->parseCertificates($assocArgs['certificates'] ?? '[]');
                $result = $service->closeEvent($eventId, $changedBy, $certificates);
            } else {
                $publication = $this->parsePublication($assocArgs['publication'] ?? '{}');
                $result = $service->publishEvent($eventId, $changedBy, $publication);
            }
        } catch (InvalidArgumentException $exception) {
            \WP_CLI::error($exception->getMessage());
            return;
        } catch (\Throwable $exception) {
            \WP_CLI::error('Lifecycle actie mislukt: ' . $exception->getMessage());
            return;
        }

        \WP_CLI::success(sprintf('Event %d %s uitgevoerd.', $eventId, $phase));
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function buildCloseoutService(): EventCloseoutService {
        $eventService = new EventService(new EventRepository());
        $certificateService = new CertificateService(new CertificateRepository());
        $auditLogService = new AuditLogService(new AuditLogRepository());
        $templateService = new EmailTemplateService(new EmailTemplateRepository());
        $outboxService = new EmailOutboxService(new EmailOutboxRepository());
        $processor = new OutboxProcessorService($outboxService, new WpMailer());
        $notificationService = new PublicationNotificationService($templateService, $outboxService, $processor);
        $publicationService = new EventPublicationService(new EventPublicationRepository());

        return new EventCloseoutService($eventService, $certificateService, $auditLogService, $notificationService, $publicationService);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseCertificates(string $json): array {
        $decoded = json_decode($json, true);
        if ($decoded === null || $decoded === '') {
            return [];
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Parameter --certificates moet valide JSON array zijn.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePublication(string $json): array {
        $decoded = json_decode($json, true);
        if ($decoded === null || $decoded === '') {
            return [];
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Parameter --publication moet valide JSON object zijn.');
        }

        return $decoded;
    }
}
