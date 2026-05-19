<?php

/**
 * ConsentVerification component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Consent;

use ArtisanPackUI\Compliance\Models\ConsentRecord;

class ConsentVerification
{
    public function __construct(
        public readonly bool $isValid,
        public readonly ?ConsentRecord $record = null,
        public readonly ?string $reason = null,
        public readonly bool $requiresReconsent = false,
        public readonly ?string $policyVersion = null,
    ) {
    }

    /**
     * Create a valid verification result.
     */
    public static function valid( ConsentRecord $record ): self
    {
        return new self(
            isValid: true,
            record: $record,
            policyVersion: $record->policy_version,
        );
    }

    /**
     * Create an invalid verification result.
     */
    public static function invalid( string $reason, bool $requiresReconsent = false ): self
    {
        return new self(
            isValid: false,
            reason: $reason,
            requiresReconsent: $requiresReconsent,
        );
    }

    /**
     * Create a result indicating reconsent is required.
     */
    public static function reconsentRequired( ConsentRecord $record, string $reason ): self
    {
        return new self(
            isValid: false,
            record: $record,
            reason: $reason,
            requiresReconsent: true,
            policyVersion: $record->policy_version,
        );
    }
}
