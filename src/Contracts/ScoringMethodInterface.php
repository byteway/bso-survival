<?php

namespace BSO\Survival\Contracts;

interface ScoringMethodInterface {
    public function getId(): string;

    public function getName(): string;

    public function getDescription(): string;

    /**
     * @param mixed $value
     * @param array<string, mixed> $config
     */
    public function validateRawValue($value, array $config = []): bool;

    /**
     * @param mixed $rawValue
     * @param array<string, mixed> $config
     */
    public function normalizeToPoints($rawValue, array $config = []): float;

    /**
     * @param array<int, float|int> $teamScores
     * @return array<int, int>
     */
    public function generatePositionProposal(array $teamScores): array;

    public function getFieldType(): string;

    public function getFieldUnit(): string;
}
