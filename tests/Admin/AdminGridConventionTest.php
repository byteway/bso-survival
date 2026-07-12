<?php

namespace BSO\Survival\Tests\Admin;

use PHPUnit\Framework\TestCase;

class AdminGridConventionTest extends TestCase {
    /** @test */
    public function interactive_admin_grids_keep_required_interaction_and_sorting_conventions(): void {
        $files = [
            __DIR__ . '/../../src/Admin/DashboardMessageAdminPage.php',
            __DIR__ . '/../../src/Admin/ScoreEntryAdminPage.php',
            __DIR__ . '/../../src/Admin/PartAdminPage.php',
            __DIR__ . '/../../src/Admin/RegistrationAdminPage.php',
        ];

        foreach ($files as $filePath) {
            $this->assertFileExists($filePath, 'Expected admin page file not found: ' . $filePath);

            $content = (string) file_get_contents($filePath);
            $this->assertNotSame('', $content, 'Expected readable admin page content for: ' . $filePath);

            // Interactive rows stay keyboard accessible.
            $this->assertStringContainsString('tabindex="0"', $content, 'Missing tabindex row accessibility in: ' . $filePath);
            $this->assertStringContainsString('addEventListener("keydown"', $content, 'Missing keydown handler for row activation in: ' . $filePath);

            // Sorting keeps always-visible arrows and active direction arrows.
            $this->assertStringContainsString('↕', $content, 'Missing inactive sort arrow in: ' . $filePath);
            $this->assertStringContainsString('▲', $content, 'Missing active ascending sort arrow in: ' . $filePath);
            $this->assertStringContainsString('▼', $content, 'Missing active descending sort arrow in: ' . $filePath);

            // Selected-row visual state remains available.
            $this->assertStringContainsString('is-selected', $content, 'Missing selected-row class usage in: ' . $filePath);

            // Hover/focus icon indicator on first cell remains available.
            $this->assertStringContainsString('content:"↗"', $content, 'Missing hover/focus indicator icon in: ' . $filePath);
        }
    }
}
