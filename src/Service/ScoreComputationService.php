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

        return $method->normalizeToPoints($rawValue, $config);
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

        return $method->generatePositionProposal($normalized);
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
