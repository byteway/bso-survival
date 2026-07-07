<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class ScoreComputationService {
    /** @var PartRuleRepositoryInterface */
    private $rules;

    public function __construct(PartRuleRepositoryInterface $rules) {
        $this->rules = $rules;
    }

    /**
     * @param mixed $rawValue
     */
    public function normalizeRawValueForPart(int $partId, $rawValue): float {
        $rule = $this->requireRule($partId);
        $method = $this->requireMethod($rule->scoring_mode ?? '');
        $config = $this->decodeConfig($rule->scoring_config ?? null);

        $normalized = $method->normalizeToPoints($rawValue, $config);

        if (function_exists('apply_filters')) {
            $normalized = apply_filters(
                'bso_survival_score_normalized_points',
                $normalized,
                $rawValue,
                $partId,
                $rule,
                $method,
                $config
            );
        }

        return (float) $normalized;
    }

    /**
     * @param array<int, float|int> $teamRawValues
     * @return array<int, int>
     */
    public function positionProposalForPart(int $partId, array $teamRawValues): array {
        $rule = $this->requireRule($partId);
        $method = $this->requireMethod($rule->scoring_mode ?? '');
        $config = $this->decodeConfig($rule->scoring_config ?? null);

        $normalized = [];
        foreach ($teamRawValues as $teamId => $rawValue) {
            $normalized[(int) $teamId] = $method->normalizeToPoints($rawValue, $config);
        }

        $positions = $method->generatePositionProposal($normalized);

        if (function_exists('apply_filters')) {
            $positions = apply_filters(
                'bso_survival_position_proposal',
                $positions,
                $partId,
                $teamRawValues,
                $rule,
                $method,
                $config
            );
        }

        return is_array($positions) ? $positions : [];
    }

    /**
     * @return object
     */
    private function requireRule(int $partId) {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part id must be a positive integer.');
        }

        $rule = $this->rules->findByPartId($partId);
        if ($rule === null) {
            throw new RuntimeException(sprintf('No PartRule configured for part %d.', $partId));
        }

        return $rule;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeConfig($scoringConfig): array {
        if (!is_string($scoringConfig) || $scoringConfig === '') {
            return [];
        }

        $decoded = json_decode($scoringConfig, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function requireMethod(string $mode) {
        if (!ScoringMethodRegistry::exists($mode)) {
            throw new RuntimeException(sprintf('Unsupported scoring mode: %s', $mode));
        }

        $method = ScoringMethodRegistry::get($mode);
        if ($method === null) {
            throw new RuntimeException(sprintf('Scoring method %s is unavailable.', $mode));
        }

        return $method;
    }
}
