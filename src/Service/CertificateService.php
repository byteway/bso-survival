<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\CertificateRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class CertificateService {
    /** @var CertificateRepositoryInterface */
    private $certificates;

    public function __construct(CertificateRepositoryInterface $certificates) {
        $this->certificates = $certificates;
    }

    /**
     * @param array<string, mixed> $meta
     * @return object
     */
    public function generate(int $eventId, int $teamId, string $filePath, array $meta = []) {
        if ($eventId <= 0) {
            throw new InvalidArgumentException('event id must be a positive integer.');
        }

        if ($teamId <= 0) {
            throw new InvalidArgumentException('team id must be a positive integer.');
        }

        if (trim($filePath) === '') {
            throw new InvalidArgumentException('file path must not be empty.');
        }

        $payload = [
            'event_id' => $eventId,
            'team_id' => $teamId,
            'file_path' => $filePath,
            'generated_at' => gmdate('Y-m-d H:i:s'),
            'delivery_status' => 'pending',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_before_certificate_generated', $payload, $meta);
        }

        $stored = $this->certificates->insert($payload);
        if ($stored === null) {
            throw new RuntimeException('Failed to persist certificate.');
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_certificate_generated', (int) $stored->id, $eventId, $teamId, $stored, $meta);
        }

        return $stored;
    }
}
