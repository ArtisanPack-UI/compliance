<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Contracts;

use ArtisanPackUI\Compliance\Compliance\Monitoring\CheckResult;

interface ComplianceCheckInterface
{
    /**
     * Get check name.
     */
    public function getName(): string;

    /**
     * Get check description.
     */
    public function getDescription(): string;

    /**
     * Get check category.
     */
    public function getCategory(): string;

    /**
     * Get applicable regulations.
     *
     * @return array<string>
     */
    public function getRegulations(): array;

    /**
     * Run the check.
     */
    public function run(): CheckResult;

    /**
     * Check if check is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get recommended schedule (cron expression).
     */
    public function getRecommendedSchedule(): string;

    /**
     * Get severity of violations found by this check.
     */
    public function getSeverity(): string;

    /**
     * Get remediation guidance.
     */
    public function getRemediation(): string;
}
