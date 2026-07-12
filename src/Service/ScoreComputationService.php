<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\PartRuleRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class ScoreComputationService {
    /** @var PartRuleRepositoryInterface */
    private $rules;

    /** @var array<int, int> */
    private $autoCreatedPartIds = [];

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
     * @param array<int, float|int> $teamNormalizedPoints
     * @return array<int, int>
     */
    public function positionProposalFromNormalizedForPart(int $partId, array $teamNormalizedPoints): array {
        $rule = $this->requireRule($partId);
        $method = $this->requireMethod($rule->scoring_mode ?? '');
        $config = $this->decodeConfig($rule->scoring_config ?? null);

        $normalized = [];
        foreach ($teamNormalizedPoints as $teamId => $value) {
            $normalized[(int) $teamId] = (float) $value;
        }

        $positions = $method->generatePositionProposal($normalized);

        if (function_exists('apply_filters')) {
            $positions = apply_filters(
                'bso_survival_position_proposal',
                $positions,
                $partId,
                $teamNormalizedPoints,
                $rule,
                $method,
                $config
            );
        }

        return is_array($positions) ? $positions : [];
    }

    /**
     * @return array<int, int>
     */
    public function consumeAutoCreatedPartIds(): array {
        $ids = array_values(array_unique(array_map('intval', $this->autoCreatedPartIds)));
        $this->autoCreatedPartIds = [];

        return array_values(array_filter($ids, static function (int $id): bool {
            return $id > 0;
        }));
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
            $created = $this->rules->upsertForPart(
                $partId,
                'points',
                'points',
                'manual_referee',
                json_encode([
                    'normalization_curve' => 'linear',
                    'max_points' => 100,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"normalization_curve":"linear","max_points":100}'
            );

            if (!$created) {
                throw new RuntimeException(sprintf('No PartRule configured for part %d.', $partId));
            }

            $this->autoCreatedPartIds[] = $partId;

            $rule = $this->rules->findByPartId($partId);
            if ($rule === null) {
                throw new RuntimeException(sprintf('No PartRule configured for part %d.', $partId));
            }
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
