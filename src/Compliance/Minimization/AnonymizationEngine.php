<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Minimization;

use DateTime;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnonymizationEngine
{
    /**
     * @var array<string, callable>
     */
    protected array $strategies = [];

    public function __construct()
    {
        $this->registerDefaultStrategies();
    }

    /**
     * Anonymize a single value.
     */
    public function anonymizeValue( mixed $value, string $type ): mixed
    {
        if ( null === $value ) {
            return null;
        }

        if ( isset( $this->strategies[ $type ] ) ) {
            return ( $this->strategies[ $type ] )( $value );
        }

        return $this->defaultAnonymize( $value );
    }

    /**
     * Get available anonymization strategies.
     *
     * @return array<string>
     */
    public function getStrategies(): array
    {
        return array_keys( $this->strategies );
    }

    /**
     * Generalize a value (k-anonymity).
     */
    public function generalize( mixed $value, string $type, int $level = 1 ): mixed
    {
        return match ( $type ) {
            'date'     => $this->generalizeDate( $value, $level ),
            'age'      => $this->generalizeAge( $value, $level ),
            'number'   => $this->generalizeNumber( $value, $level ),
            'location' => $this->generalizeLocation( $value, $level ),
            default    => $this->defaultGeneralize( $value, $level ),
        };
    }

    /**
     * Suppress a value (remove completely).
     */
    public function suppress( mixed $value ): null
    {
        return null;
    }

    /**
     * Add noise for differential privacy.
     */
    public function addNoise( float $value, float $epsilon ): float
    {
        // Laplace mechanism for differential privacy. Uses a CSPRNG
        // (random_int) — mt_rand() doesn't give the entropy guarantees
        // differential privacy assumes — and clamps `u` away from the
        // ±0.5 boundary so 1 - 2*|u| can't hit 0 and produce log(0)/-INF.
        $scale = 1 / $epsilon;
        $u     = random_int( 1, PHP_INT_MAX - 1 ) / PHP_INT_MAX - 0.5;
        $u     = max( -0.4999999, min( 0.4999999, $u ) );
        $noise = -$scale * ( ( $u > 0 ? 1 : -1 ) * log( 1 - 2 * abs( $u ) ) );

        return $value + $noise;
    }

    /**
     * Hash with salt for pseudonymization.
     */
    public function hash( string $value, string $salt ): string
    {
        return hash( 'sha256', $value . $salt );
    }

    /**
     * Tokenize (reversible with key).
     */
    public function tokenize( string $value ): string
    {
        return encrypt( $value );
    }

    /**
     * Mask partial data.
     */
    public function mask( string $value, string $type ): string
    {
        return match ( $type ) {
            'email'       => $this->maskEmail( $value ),
            'phone'       => $this->maskPhone( $value ),
            'credit_card' => $this->maskCreditCard( $value ),
            'ssn'         => $this->maskSsn( $value ),
            default       => $this->defaultMask( $value ),
        };
    }

    /**
     * Validate k-anonymity of dataset.
     *
     * @param  array<string>  $quasiIdentifiers
     */
    public function validateKAnonymity( Collection $data, array $quasiIdentifiers, int $k ): bool
    {
        $groups = $data->groupBy( function ( $item ) use ( $quasiIdentifiers ) {
            $key = [];
            foreach ( $quasiIdentifiers as $qi ) {
                $key[] = is_array( $item ) ? ( $item[ $qi ] ?? '' ) : ( $item->$qi ?? '' );
            }

            return implode( '|', $key );
        } );

        foreach ( $groups as $group ) {
            if ( $group->count() < $k ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Register a custom anonymization strategy.
     */
    public function registerStrategy( string $type, callable $strategy ): void
    {
        $this->strategies[ $type ] = $strategy;
    }

    /**
     * Register default anonymization strategies.
     */
    protected function registerDefaultStrategies(): void
    {
        $this->strategies['email']       = fn ( $value ) => $this->maskEmail( $value );
        $this->strategies['phone']       = fn ( $value ) => $this->maskPhone( $value );
        $this->strategies['name']        = fn ( $value ) => $this->anonymizeName( $value );
        $this->strategies['address']     = fn ( $value ) => '[ADDRESS REDACTED]';
        $this->strategies['date']        = fn ( $value ) => $this->generalizeDate( $value, 1 );
        $this->strategies['number']      = fn ( $value ) => $this->generalizeNumber( $value, 1 );
        $this->strategies['ip']          = fn ( $value ) => $this->anonymizeIp( $value );
        $this->strategies['credit_card'] = fn ( $value ) => $this->maskCreditCard( $value );
        $this->strategies['ssn']         = fn ( $value ) => $this->maskSsn( $value );
    }

    /**
     * Default anonymization for unknown types.
     */
    protected function defaultAnonymize( mixed $value ): mixed
    {
        if ( is_string( $value ) ) {
            $length = strlen( $value );
            if ( $length <= 2 ) {
                return str_repeat( '*', $length );
            }

            return Str::mask( $value, '*', 1, $length - 2 );
        }

        if ( is_numeric( $value ) ) {
            return $this->generalizeNumber( $value, 1 );
        }

        return '[REDACTED]';
    }

    /**
     * Mask email address.
     */
    protected function maskEmail( string $email ): string
    {
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return $this->defaultMask( $email );
        }

        [$local, $domain] = explode( '@', $email );
        $maskedLocal      = strlen( $local ) > 2
            ? $local[0] . str_repeat( '*', strlen( $local ) - 2 ) . $local[-1]
            : str_repeat( '*', strlen( $local ) );

        return $maskedLocal . '@' . $domain;
    }

    /**
     * Mask phone number.
     */
    protected function maskPhone( string $phone ): string
    {
        $digits = preg_replace( '/[^\d]/', '', $phone );
        $length = strlen( $digits );

        if ( $length <= 4 ) {
            return str_repeat( '*', $length );
        }

        return str_repeat( '*', $length - 4 ) . substr( $digits, -4 );
    }

    /**
     * Mask credit card number.
     */
    protected function maskCreditCard( string $card ): string
    {
        $digits = preg_replace( '/[^\d]/', '', $card );
        if ( strlen( $digits ) <= 4 ) {
            return str_repeat( '*', strlen( $digits ) );
        }

        return str_repeat( '*', strlen( $digits ) - 4 ) . substr( $digits, -4 );
    }

    /**
     * Mask SSN.
     */
    protected function maskSsn( string $ssn ): string
    {
        $digits = preg_replace( '/[^\d]/', '', $ssn );
        if ( strlen( $digits ) < 4 ) {
            return str_repeat( '*', strlen( $digits ) );
        }

        return '***-**-' . substr( $digits, -4 );
    }

    /**
     * Default masking.
     */
    protected function defaultMask( string $value ): string
    {
        $length = strlen( $value );
        if ( $length <= 4 ) {
            return str_repeat( '*', $length );
        }

        return substr( $value, 0, 2 ) . str_repeat( '*', $length - 4 ) . substr( $value, -2 );
    }

    /**
     * Anonymize a name.
     */
    protected function anonymizeName( string $name ): string
    {
        $parts = explode( ' ', $name );

        return implode( ' ', array_map( function ( $part ) {
            return strlen( $part ) > 0 ? $part[0] . str_repeat( '*', strlen( $part ) - 1 ) : '';
        }, $parts ) );
    }

    /**
     * Anonymize an IP address.
     */
    protected function anonymizeIp( string $ip ): string
    {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $parts    = explode( '.', $ip );
            $parts[3] = '0';

            return implode( '.', $parts );
        }

        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            // Convert to binary, zero out last 64 bits (8 bytes), convert back
            // This handles both full and compressed IPv6 notation correctly
            $binary = inet_pton( $ip );
            if ( false === $binary ) {
                return '::';
            }

            // Zero out the last 8 bytes (64 bits) for /64 anonymization
            $binary = substr( $binary, 0, 8 ) . str_repeat( "\0", 8 );

            $anonymized = inet_ntop( $binary );

            return false !== $anonymized ? $anonymized : '::';
        }

        return '0.0.0.0';
    }

    /**
     * Generalize a date.
     */
    protected function generalizeDate( mixed $value, int $level ): string
    {
        try {
            $date = $value instanceof DateTimeInterface
                ? $value
                : new DateTime( $value );
        } catch ( Exception $e ) {
            // Malformed date input shouldn't crash the whole
            // anonymization pass — return an explicit marker so the
            // record is visibly redacted rather than silently broken.
            return '[INVALID DATE]';
        }

        return match ( $level ) {
            1       => $date->format( 'Y-m' ),  // Month/Year
            2       => $date->format( 'Y' ),     // Year only
            3       => (string) ( floor( (int) $date->format( 'Y' ) / 5 ) * 5 ) . '-' . (string) ( floor( (int) $date->format( 'Y' ) / 5 ) * 5 + 4 ), // 5-year range
            default => $date->format( 'Y-m' ),
        };
    }

    /**
     * Generalize age.
     */
    protected function generalizeAge( int $age, int $level ): string
    {
        $range = match ( $level ) {
            1       => 5,
            2       => 10,
            3       => 20,
            default => 5,
        };

        $lower = floor( $age / $range ) * $range;
        $upper = $lower + $range - 1;

        return "{$lower}-{$upper}";
    }

    /**
     * Generalize a number.
     */
    protected function generalizeNumber( mixed $value, int $level ): mixed
    {
        $value     = (float) $value;
        $magnitude = pow( 10, $level );

        return floor( $value / $magnitude ) * $magnitude;
    }

    /**
     * Generalize location (zip code).
     */
    protected function generalizeLocation( string $value, int $level ): string
    {
        $length = strlen( $value );
        $keep   = max( 1, $length - $level );

        return substr( $value, 0, $keep ) . str_repeat( '*', $length - $keep );
    }

    /**
     * Default generalization.
     */
    protected function defaultGeneralize( mixed $value, int $level ): mixed
    {
        if ( is_string( $value ) ) {
            return substr( $value, 0, max( 1, strlen( $value) - $level));
        }

        return $value;
    }
}
