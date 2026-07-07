<?php

namespace BSO\Survival\Service;

use BSO\Survival\Contracts\ScoringMethodInterface;
use BSO\Survival\Service\ScoringMethods\DistanceScoringMethod;
use BSO\Survival\Service\ScoringMethods\PointsScoringMethod;
use BSO\Survival\Service\ScoringMethods\TimeScoringMethod;

class ScoringMethodRegistry {
    /** @var array<string, ScoringMethodInterface> */
    private static $methods = [];

    public static function register(string $id, ScoringMethodInterface $method): void {
        self::$methods[$id] = $method;
    }

    public static function get(string $id): ?ScoringMethodInterface {
        return self::$methods[$id] ?? null;
    }

    /**
     * @return array<string, ScoringMethodInterface>
     */
    public static function all(): array {
        return self::$methods;
    }

    public static function exists(string $id): bool {
        return isset(self::$methods[$id]);
    }

    public static function initDefaults(): void {
        self::register('time', new TimeScoringMethod());
        self::register('points', new PointsScoringMethod());
        self::register('distance', new DistanceScoringMethod());

        if (function_exists('do_action')) {
            do_action('bso_survival_register_scoring_methods', self::class);
        }
    }

    public static function reset(): void {
        self::$methods = [];
    }
}
