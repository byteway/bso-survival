<?php

namespace BSO\Survival\Service;

use InvalidArgumentException;

class RankingService {
    /** @var ScoreComputationService */
    private $scoring;

    public function __construct(ScoreComputationService $scoring) {
        $this->scoring = $scoring;
    }

    /**
     * @param array<int, float|int> $teamRawValues
     * @return array<int, int>
     */
    public function refreshForPart(int $partId, array $teamRawValues): array {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part id must be a positive integer.');
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_before_ranking_refresh', $partId, $teamRawValues);
        }

        $positions = $this->scoring->positionProposalForPart($partId, $teamRawValues);

        if (function_exists('do_action')) {
            do_action('bso_survival_ranking_updated', $partId, $positions, $teamRawValues);
        }

        return $positions;
    }

    /**
     * @param array<int, float|int> $teamNormalizedPoints
     * @return array<int, int>
     */
    public function refreshForPartWithNormalized(int $partId, array $teamNormalizedPoints): array {
        if ($partId <= 0) {
            throw new InvalidArgumentException('part id must be a positive integer.');
        }

        if (function_exists('do_action')) {
            do_action('bso_survival_before_ranking_refresh', $partId, $teamNormalizedPoints);
        }

        $positions = $this->scoring->positionProposalFromNormalizedForPart($partId, $teamNormalizedPoints);

        if (function_exists('do_action')) {
            do_action('bso_survival_ranking_updated', $partId, $positions, $teamNormalizedPoints);
        }

        return $positions;
    }
}
