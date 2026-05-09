<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Monitoring\Checks;

use ArtisanPackUI\Compliance\Compliance\Contracts\ComplianceCheckInterface;
use ArtisanPackUI\Compliance\Compliance\Monitoring\CheckResult;
use InvalidArgumentException;

abstract class BaseComplianceCheck implements ComplianceCheckInterface
{
    protected string $name = '';

    protected string $description = '';

    protected string $category = 'general';

    /**
     * @var array<string>
     */
    protected array $regulations = ['gdpr'];

    protected string $severity = 'medium';

    protected string $schedule = '0 */6 * * *';

    protected string $remediation = '';

    protected bool $enabled = true;

    /**
     * Get check name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get check description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get check category.
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Get applicable regulations.
     *
     * @return array<string>
     */
    public function getRegulations(): array
    {
        return $this->regulations;
    }

    /**
     * Check if check is enabled.
     *
     * @throws InvalidArgumentException
     */
    public function isEnabled(): bool
    {
        $this->validateName();

        $configKey = 'artisanpack.compliance.compliance.monitoring.checks.' . $this->name . '.enabled';

        return config( $configKey, $this->enabled );
    }

    /**
     * Get recommended schedule.
     */
    public function getRecommendedSchedule(): string
    {
        return $this->schedule;
    }

    /**
     * Get severity.
     *
     * @throws InvalidArgumentException
     */
    public function getSeverity(): string
    {
        $this->validateName();

        return config(
            'artisanpack.compliance.compliance.monitoring.checks.' . $this->name . '.severity',
            $this->severity,
        );
    }

    /**
     * Get remediation guidance.
     */
    public function getRemediation(): string
    {
        return $this->remediation;
    }

    /**
     * Validate that the check name is non-empty.
     *
     * @throws InvalidArgumentException
     */
    protected function validateName(): void
    {
        if ( empty( $this->name ) ) {
            throw new InvalidArgumentException(
                'Compliance check name cannot be empty. Ensure the $name property is set in the concrete check class.',
            );
        }
    }

    /**
     * Create a passed result.
     *
     * @param  array<string, mixed>  $details
     */
    protected function passed( int $checked = 0, int $compliant = 0, array $details = [] ): CheckResult
    {
        return CheckResult::passed( $checked, $compliant, $details );
    }

    /**
     * Create a failed result.
     *
     * @param  array<string>  $violations
     * @param  array<string, mixed>  $details
     */
    protected function failed( array $violations, int $checked = 0, int $compliant = 0, array $details = [] ): CheckResult
    {
        return CheckResult::failed( $violations, $checked, $compliant, $details );
    }

    /**
     * Create a warning result.
     *
     * @param  array<string>  $warnings
     * @param  array<string, mixed>  $details
     */
    protected function warning( array $warnings, int $checked = 0, int $compliant = 0, array $details = [] ): CheckResult
    {
        return CheckResult::warning( $warnings, $checked, $compliant, $details );
    }
}
