<?php

namespace BSO\Survival\Support;

class Capabilities {
    public const MANAGE_SCORES = 'manage_survival_scores';
    public const MANAGE_MESSAGES = 'manage_survival_messages';
    private const ADMIN_FALLBACK = 'manage_options';

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

        $adminRole->add_cap(self::MANAGE_SCORES);
        $adminRole->add_cap(self::MANAGE_MESSAGES);
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
