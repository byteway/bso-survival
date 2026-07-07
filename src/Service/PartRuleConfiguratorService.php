<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class PartRuleConfiguratorService {
    private const ALLOWED_TIEBREAKERS = [
        'manual_referee',
        'lower_raw_wins',
        'higher_raw_wins',
    ];

    private const ALLOWED_CURVES = [
        'linear',
    ];

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

        $validatedTiebreaker = $this->sanitizeTiebreakerMode($tiebreakerMode);
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
            $validatedTiebreaker,
            $configJson
        );
    }

    private function sanitizeTiebreakerMode(string $tiebreakerMode): string {
        $value = trim($tiebreakerMode);

        if (!in_array($value, self::ALLOWED_TIEBREAKERS, true)) {
            return 'manual_referee';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function sanitizeConfigByMode(string $mode, array $config): array {
        $curve = isset($config['normalization_curve'])
            ? trim((string) $config['normalization_curve'])
            : 'linear';

        if (!in_array($curve, self::ALLOWED_CURVES, true)) {
            $curve = 'linear';
        }

        $safe = [
            'normalization_curve' => $curve,
        ];

        if ($mode === 'time') {
            $safe['max_time'] = isset($config['max_time']) ? max(1, (int) $config['max_time']) : 1200;
        }

        if ($mode === 'points') {
            $safe['max_points'] = isset($config['max_points']) ? max(1, (int) $config['max_points']) : 100;
        }

        if ($mode === 'distance') {
            $safe['max_distance'] = isset($config['max_distance']) ? max(1, (int) $config['max_distance']) : 500;
        }

        return $safe;
    }
}
