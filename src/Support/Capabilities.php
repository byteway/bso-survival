<?php

namespace BSO\Survival\Support;

class Capabilities {
    public const MANAGE_SETTINGS = 'manage_survival_settings';
    public const MANAGE_ACCESS = 'manage_survival_access';
    public const MANAGE_SCORES = 'manage_survival_scores';
    public const MANAGE_MESSAGES = 'manage_survival_messages';
    private const ADMIN_FALLBACK = 'manage_options';

    /** @var array<int, string> */
    private const SURVIVAL_CAPABILITIES = [
        self::MANAGE_SETTINGS,
        self::MANAGE_ACCESS,
        self::MANAGE_SCORES,
        self::MANAGE_MESSAGES,
    ];

    public static function canManageSettings(): bool {
        return self::currentUserCan(self::MANAGE_SETTINGS);
    }

    public static function canManageAccess(): bool {
        return self::currentUserCan(self::MANAGE_ACCESS);
    }

    public static function canManageScores(): bool {
        return self::currentUserCan(self::MANAGE_SCORES);
    }

    public static function canManageMessages(): bool {
        return self::currentUserCan(self::MANAGE_MESSAGES);
    }

    public static function ensureRoleMappings(): void {
        if (!function_exists('get_role')) {
            return;
        }

        $adminRole = get_role('administrator');
        if (!is_object($adminRole) || !method_exists($adminRole, 'add_cap')) {
            return;
        }

        foreach (self::SURVIVAL_CAPABILITIES as $capability) {
            $adminRole->add_cap($capability);
        }
    }

    /**
     * @return array<int, string>
     */
    public static function allSurvivalCapabilities(): array {
        return self::SURVIVAL_CAPABILITIES;
    }

    private static function currentUserCan(string $capability): bool {
        if (!function_exists('current_user_can')) {
            return false;
        }

        if (current_user_can($capability)) {
            return true;
        }

        return current_user_can(self::ADMIN_FALLBACK);
    }
}
