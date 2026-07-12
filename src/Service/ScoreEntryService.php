<?php

namespace BSO\Survival\Service;

use BSO\Survival\Database\Repository\ScoreEntryRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class ScoreEntryService {
    /** @var ScoreEntryRepositoryInterface */
    private $entries;

    /** @var ScoreComputationService */
    private $scoring;

    public function __construct(ScoreEntryRepositoryInterface $entries, ScoreComputationService $scoring) {
        $this->entries = $entries;
        $this->scoring = $scoring;
    }

    /**
     * @param mixed $rawValue
     * @param mixed $bonusPoints
     * @param array<string, mixed> $context
     * @return object
     */
    public function submit(int $partId, int $assignmentId, $rawValue, $bonusPoints, string $enteredByRole, array $context = []) {
        $entry = [
            'part_id' => $partId,
            'assignment_id' => $assignmentId,
            'raw_value' => $rawValue,
            'bonus_points' => $bonusPoints,
            'entered_by_role' => $enteredByRole,
            'context' => $context,
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_before_score_validation', $entry);
        }

        $this->validateSubmission($partId, $assignmentId, $rawValue, $bonusPoints, $enteredByRole);

        $normalizedBase = $this->scoring->normalizeRawValueForPart($partId, $rawValue);
        $normalizedPoints = $this->composeNormalizedPoints($normalizedBase, $bonusPoints);
        $stored = $this->entries->insert([
            'assignment_id' => $assignmentId,
            'raw_value' => $rawValue,
            'bonus_points' => (float) $bonusPoints,
            'normalized_points' => $normalizedPoints,
            'position' => null,
            'rank_points' => null,
            'joker_applied' => 0,
            'entered_by_role' => $enteredByRole,
            'entered_at' => gmdate('Y-m-d H:i:s'),
            'status' => 'concept',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($stored === null) {
            throw new RuntimeException('Failed to persist score entry.');
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_score_recorded', (int) $stored->id, $assignmentId, $rawValue, $stored);
        }

        return $stored;
    }

    /**
     * @param mixed $rawValue
     * @param mixed $bonusPoints
     * @param array<string, mixed> $context
     * @return object
     */
    public function updateEntry(int $scoreEntryId, int $partId, int $assignmentId, $rawValue, $bonusPoints, string $enteredByRole, array $context = []) {
        if ($scoreEntryId <= 0) {
            throw new InvalidArgumentException('score_entry_id must be a positive integer.');
        }

        $existing = $this->entries->findById($scoreEntryId);
        if ($existing === null) {
            throw new InvalidArgumentException(sprintf('score entry %d not found.', $scoreEntryId));
        }

        $entry = [
            'id' => $scoreEntryId,
            'part_id' => $partId,
            'assignment_id' => $assignmentId,
            'raw_value' => $rawValue,
            'bonus_points' => $bonusPoints,
            'entered_by_role' => $enteredByRole,
            'context' => $context,
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_before_score_validation', $entry);
        }

        $this->validateSubmission($partId, $assignmentId, $rawValue, $bonusPoints, $enteredByRole);

        $normalizedBase = $this->scoring->normalizeRawValueForPart($partId, $rawValue);
        $normalizedPoints = $this->composeNormalizedPoints($normalizedBase, $bonusPoints);
        $updated = $this->entries->updateById($scoreEntryId, [
            'assignment_id' => $assignmentId,
            'raw_value' => $rawValue,
            'bonus_points' => (float) $bonusPoints,
            'normalized_points' => $normalizedPoints,
            'entered_by_role' => $enteredByRole,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($updated === null) {
            throw new RuntimeException('Failed to update score entry.');
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_score_recorded', $scoreEntryId, $assignmentId, $rawValue, $updated);
        }

        return $updated;
    }

    /**
     * @return array<int, int>
     */
    public function consumeAutoCreatedPartIds(): array {
        return $this->scoring->consumeAutoCreatedPartIds();
    }

    /**
     * @param mixed $rawValue
     * @param mixed $bonusPoints
     */
    private function validateSubmission(int $partId, int $assignmentId, $rawValue, $bonusPoints, string $enteredByRole): void {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part id must be a positive integer.');
        }

        if ($assignmentId <= 0) {
            throw new InvalidArgumentException('assignment id must be a positive integer.');
        }

        if (!is_numeric($rawValue)) {
            throw new InvalidArgumentException('raw value must be numeric.');
        }

        if (!is_numeric($bonusPoints) || (float) $bonusPoints < 0) {
            throw new InvalidArgumentException('bonus_points must be numeric and >= 0.');
        }

        if (trim($enteredByRole) === '') {
            throw new InvalidArgumentException('entered_by_role is required.');
        }
    }

    /**
     * @param mixed $bonusPoints
     */
    private function composeNormalizedPoints(float $normalizedBase, $bonusPoints): float {
        $bonus = is_numeric($bonusPoints) ? (float) $bonusPoints : 0.0;

        return $normalizedBase + max(0.0, $bonus);
    }
}
