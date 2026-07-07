<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class PartRuleConfiguratorService {
    /** @var PartRuleRepositoryInterface */
    private $rules;

    public function __construct(PartRuleRepositoryInterface $rules) {
        $this->rules = $rules;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function configure(int $partId, string $scoringMode, array $config, string $tiebreakerMode = 'manual_referee'): bool {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part id must be a positive integer.');
        }

        $mode = trim($scoringMode);
        if (!ScoringMethodRegistry::exists($mode)) {
            throw new InvalidArgumentException(sprintf('Unsupported scoring mode: %s', $mode));
        }

        $method = ScoringMethodRegistry::get($mode);
        if ($method === null) {
            throw new RuntimeException(sprintf('Scoring method %s is not available.', $mode));
        }

        $validatedConfig = $this->sanitizeConfigByMode($mode, $config);
        $configJson = function_exists('wp_json_encode')
            ? wp_json_encode($validatedConfig)
            : json_encode($validatedConfig);
        if (!is_string($configJson)) {
            throw new RuntimeException('Failed to encode scoring_config.');
        }

        return $this->rules->upsertForPart(
            $partId,
            $mode,
            $method->getFieldUnit(),
            $tiebreakerMode,
            $configJson
        );
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function sanitizeConfigByMode(string $mode, array $config): array {
        $safe = [
            'normalization_curve' => isset($config['normalization_curve'])
                ? (string) $config['normalization_curve']
                : 'linear',
        ];

        if ($mode === 'time') {
            $safe['max_time'] = isset($config['max_time']) ? max(0, (int) $config['max_time']) : 1200;
        }

        if ($mode === 'points') {
            $safe['max_points'] = isset($config['max_points']) ? max(0, (int) $config['max_points']) : 100;
        }

        if ($mode === 'distance') {
            $safe['max_distance'] = isset($config['max_distance']) ? max(0, (int) $config['max_distance']) : 500;
        }

        return $safe;
    }
}
