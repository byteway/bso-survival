<?php

namespace BSO\Survival\Tests\Service;

use BSO\Survival\Support\Capabilities;
use PHPUnit\Framework\TestCase;

class CapabilitiesTest extends TestCase {
    protected function tearDown(): void {
        reset_test_current_user_caps();
    }

    /** @test */
    public function it_allows_users_with_score_capability(): void {
        set_test_current_user_caps([
            'manage_survival_scores' => true,
            'manage_options' => false,
        ]);

        $this->assertTrue(Capabilities::canManageScores());
    }

    /** @test */
    public function it_allows_users_with_message_capability(): void {
        set_test_current_user_caps([
            'manage_survival_messages' => true,
            'manage_options' => false,
        ]);

        $this->assertTrue(Capabilities::canManageMessages());
    }

    /** @test */
    public function it_keeps_manage_options_as_backwards_compatible_fallback(): void {
        set_test_current_user_caps([
            'manage_survival_scores' => false,
            'manage_survival_messages' => false,
            'manage_options' => true,
        ]);

        $this->assertTrue(Capabilities::canManageScores());
        $this->assertTrue(Capabilities::canManageMessages());
    }

    /** @test */
    public function it_denies_when_neither_capability_nor_admin_fallback_is_present(): void {
        set_test_current_user_caps([
            'manage_survival_scores' => false,
            'manage_survival_messages' => false,
            'manage_options' => false,
        ]);

        $this->assertFalse(Capabilities::canManageScores());
        $this->assertFalse(Capabilities::canManageMessages());
    }
}
