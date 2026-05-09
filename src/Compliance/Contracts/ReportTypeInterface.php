<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Contracts;

use ArtisanPackUI\Compliance\Compliance\Reporting\ComplianceReport;

interface ReportTypeInterface
{
    /**
     * Get report type name.
     */
    public function getName(): string;

    /**
     * Get report type description.
     */
    public function getDescription(): string;

    /**
     * Get report category.
     */
    public function getCategory(): string;

    /**
     * Generate the report.
     *
     * @param  array<string, mixed>  $options
     */
    public function generate( array $options = [] ): ComplianceReport;

    /**
     * Get available options for this report.
     *
     * @return array<string, mixed>
     */
    public function getAvailableOptions(): array;

    /**
     * Get supported export formats.
     *
     * @return array<string>
     */
    public function getSupportedFormats(): array;

    /**
     * Get default schedule for this report type.
     */
    public function getDefaultSchedule(): ?string;
}
