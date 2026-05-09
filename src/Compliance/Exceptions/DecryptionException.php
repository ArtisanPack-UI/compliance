<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a sensitive attribute can't be decrypted at read time.
 *
 * Distinguishes "stored ciphertext is unreadable" (rotated app key,
 * legacy / tampered payload) from "value is plaintext or absent",
 * so callers can refuse to export, pseudonymize, or otherwise act
 * on opaque ciphertext as if it were a real value.
 */
class DecryptionException extends RuntimeException
{
    public function __construct(
        public readonly string $attribute,
        public readonly string $modelClass,
        public readonly mixed $modelKey,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'PrivacyByDesign: failed to decrypt sensitive attribute "%s" on %s#%s '
                    . '(unreadable ciphertext — possible app-key rotation or tampered payload).',
                $attribute,
                $modelClass,
                (string) ( $modelKey ?? 'unsaved' ),
            ),
            0,
            $previous,
        );
    }
}
