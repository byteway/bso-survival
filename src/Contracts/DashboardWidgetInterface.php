<?php

namespace BSO\Survival\Contracts;

interface DashboardWidgetInterface {
    public function getId(): string;

    public function getTitle(): string;

    public function getPriority(): int;

    /**
     * @return array<int, string>
     */
    public function getCapabilities(): array;

    /**
     * @param array<string, mixed> $overview
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getData(array $overview, array $filters = []): array;

    /**
     * @param array<string, mixed> $context
     */
    public function render(array $context): string;

    /**
     * @return array<int, string>
     */
    public function getScriptDependencies(): array;

    /**
     * @return array<int, string>
     */
    public function getStyleDependencies(): array;
}
