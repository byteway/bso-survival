<?php

namespace BSO\Survival\Service\ScoringMethods;

use BSO\Survival\Contracts\ScoringMethodInterface;

class TimeScoringMethod implements ScoringMethodInterface {
    public function getId(): string {
        return 'time';
    }

    public function getName(): string {
        return 'Tijd (seconden)';
    }

    public function getDescription(): string {
        return 'Snelste tijd wint. Team met laagste tijd krijgt meeste punten.';
    }

    public function validateRawValue($value, array $config = []): bool {
        return is_numeric($value) && (float) $value >= 0;
    }

    public function normalizeToPoints($rawValue, array $config = []): float {
        if (!$this->validateRawValue($rawValue, $config)) {
            return 0.0;
        }

        $maxTime = isset($config['max_time']) && is_numeric($config['max_time'])
            ? (float) $config['max_time']
            : 1200.0;

        $time = (float) $rawValue;
        if ($maxTime <= 0 || $time > $maxTime) {
            return 0.0;
        }

        $score = (100.0 * ($maxTime - $time)) / $maxTime;

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
        return 'time';
    }

    public function getFieldUnit(): string {
        return 'seconden';
    }
}
