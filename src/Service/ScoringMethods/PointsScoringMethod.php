<?php

namespace BSO\Survival\Service\ScoringMethods;

use BSO\Survival\Contracts\ScoringMethodInterface;

class PointsScoringMethod implements ScoringMethodInterface {
    public function getId(): string {
        return 'points';
    }

    public function getName(): string {
        return 'Punten';
    }

    public function getDescription(): string {
        return 'Meer punten is beter. Team met hoogste score krijgt meeste rankpunten.';
    }

    public function validateRawValue($value, array $config = []): bool {
        return is_numeric($value) && (float) $value >= 0;
    }

    public function normalizeToPoints($rawValue, array $config = []): float {
        if (!$this->validateRawValue($rawValue, $config)) {
            return 0.0;
        }

        $maxPoints = isset($config['max_points']) && is_numeric($config['max_points'])
            ? (float) $config['max_points']
            : 100.0;

        $points = (float) $rawValue;
        if ($maxPoints <= 0) {
            return 0.0;
        }

        $score = (100.0 * $points) / $maxPoints;

        return max(0.0, min(100.0, $score));
    }

    public function generatePositionProposal(array $teamScores): array {
        arsort($teamScores);

        $positions = [];
        $rank = 1;
        foreach ($teamScores as $teamId => $score) {
            $positions[(int) $teamId] = $rank++;
        }

        return $positions;
    }

    public function getFieldType(): string {
        return 'number';
    }

    public function getFieldUnit(): string {
        return 'punten';
    }
}
