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
     * @param array<string, mixed> $context
     * @return object
     */
    public function submit(int $partId, int $assignmentId, $rawValue, string $enteredByRole, array $context = []) {
        $entry = [
            'part_id' => $partId,
            'assignment_id' => $assignmentId,
            'raw_value' => $rawValue,
            'entered_by_role' => $enteredByRole,
            'context' => $context,
        ];

        if (function_exists('do_action')) {
            do_action('bso_survival_before_score_validation', $entry);
        }

        $this->validateSubmission($partId, $assignmentId, $rawValue, $enteredByRole);

        $normalizedPoints = $this->scoring->normalizeRawValueForPart($partId, $rawValue);
        $stored = $this->entries->insert([
            'assignment_id' => $assignmentId,
            'raw_value' => $rawValue,
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
     */
    private function validateSubmission(int $partId, int $assignmentId, $rawValue, string $enteredByRole): void {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part id must be a positive integer.');
        }

        if ($assignmentId <= 0) {
            throw new InvalidArgumentException('assignment id must be a positive integer.');
        }

        if (!is_numeric($rawValue)) {
            throw new InvalidArgumentException('raw value must be numeric.');
        }

        if (trim($enteredByRole) === '') {
            throw new InvalidArgumentException('entered_by_role is required.');
        }
    }
}
