<?php

/**
 * CookieConsentHandler component of the Compliance package.
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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class CookieConsentHandler
{
    protected const CONSENT_COOKIE_NAME = 'cookie_consent';

    protected const CONSENT_COOKIE_LIFETIME = 365; // days

    /**
     * Get the current cookie consent status.
     *
     * @return array<string, bool>
     */
    public function getConsentStatus( Request $request ): array
    {
        $cookie = $request->cookie( self::CONSENT_COOKIE_NAME );

        if ( ! $cookie ) {
            return $this->getDefaultStatus();
        }

        $decoded = json_decode( $cookie, true );

        if ( JSON_ERROR_NONE !== json_last_error() || !is_array( $decoded ) ) {
            return $this->getDefaultStatus();
        }

        return $decoded;
    }

    /**
     * Check if user has given consent for a specific category.
     */
    public function hasConsent( Request $request, string $category ): bool
    {
        $status = $this->getConsentStatus( $request );

        // Essential cookies are always allowed
        if ( 'essential' === $category ) {
            return true;
        }

        return $status[ $category ] ?? false;
    }

    /**
     * Set cookie consent status.
     *
     * @param  array<string, bool>  $choices
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function setConsent( array $choices ): \Symfony\Component\HttpFoundation\Cookie
    {
        // Essential is always true
        $choices['essential'] = true;

        // Throw on encode failure so we never write a `false` value
        // into a consent cookie — corrupted consent state would be
        // worse than refusing to set the cookie at all.
        $value = json_encode( $choices, JSON_THROW_ON_ERROR );

        return Cookie::make(
            self::CONSENT_COOKIE_NAME,
            $value,
            self::CONSENT_COOKIE_LIFETIME * 24 * 60, // convert days to minutes
            '/',
            null,
            true, // secure
            true, // httpOnly
            false, // raw
            'Strict', // sameSite
        );
    }

    /**
     * Accept all cookies.
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function acceptAll(): \Symfony\Component\HttpFoundation\Cookie
    {
        $categories = $this->getCategories();
        $choices    = [];

        foreach ( $categories as $category ) {
            $choices[ $category ] = true;
        }

        return $this->setConsent( $choices );
    }

    /**
     * Accept only essential cookies.
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function acceptEssentialOnly(): \Symfony\Component\HttpFoundation\Cookie
    {
        $categories = $this->getCategories();
        $choices    = [];

        foreach ( $categories as $category ) {
            $choices[ $category ] = 'essential' === $category;
        }

        return $this->setConsent( $choices );
    }

    /**
     * Check if consent has been given (any choice made).
     */
    public function hasConsentBeenGiven( Request $request ): bool
    {
        return null !== $request->cookie( self::CONSENT_COOKIE_NAME );
    }

    /**
     * Get available cookie categories.
     *
     * @return array<string>
     */
    public function getCategories(): array
    {
        return config( 'artisanpack.compliance.consent.cookie_consent.categories', [
            'essential',
            'functional',
            'analytics',
            'marketing',
        ] );
    }

    /**
     * Get the consent cookie configuration.
     *
     * @return array<string, mixed>
     */
    public function getCookieConfig(): array
    {
        return [
            'name'            => self::CONSENT_COOKIE_NAME,
            'lifetime'        => self::CONSENT_COOKIE_LIFETIME,
            'categories'      => $this->getCategories(),
            'banner_position' => config( 'artisanpack.compliance.consent.cookie_consent.banner_position', 'bottom' ),
        ];
    }

    /**
     * Clear consent cookie.
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function clearConsent(): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::forget( self::CONSENT_COOKIE_NAME );
    }

    /**
     * Get default consent status (no consent given).
     *
     * @return array<string, bool>
     */
    protected function getDefaultStatus(): array
    {
        $categories = $this->getCategories();
        $status     = [];

        foreach ( $categories as $category ) {
            // Only essential is true by default
            $status[ $category ] = 'essential' === $category;
        }

        return $status;
    }
}
