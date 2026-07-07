<?php

namespace BSO\Survival\Service\ScoringMethods;

use BSO\Survival\Contracts\ScoringMethodInterface;

class DistanceScoringMethod implements ScoringMethodInterface {
    public function getId(): string {
        return 'distance';
    }

    public function getName(): string {
        return 'Afstand';
    }

    public function getDescription(): string {
        return 'Grotere afstand is beter. Team met grootste afstand krijgt meeste rankpunten.';
    }

    public function validateRawValue($value, array $config = []): bool {
        return is_numeric($value) && (float) $value >= 0;
    }

    public function normalizeToPoints($rawValue, array $config = []): float {
        if (!$this->validateRawValue($rawValue, $config)) {
            return 0.0;
        }

        $maxDistance = isset($config['max_distance']) && is_numeric($config['max_distance'])
            ? (float) $config['max_distance']
            : 500.0;

        $distance = (float) $rawValue;
        if ($maxDistance <= 0) {
            return 0.0;
        }

        $score = (100.0 * $distance) / $maxDistance;

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
        return 'meter';
    }
}
