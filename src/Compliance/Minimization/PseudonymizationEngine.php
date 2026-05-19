<?php

/**
 * PseudonymizationEngine component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Minimization;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PseudonymizationEngine
{
	/**
	 * Cache key for tracking all mapping keys (for cleanup).
	 */
	protected const MAPPING_INDEX_KEY = 'pseudonym_mapping_keys_index';

	protected string $currentMappingKey;

	protected bool $storeMappings = true;

	/**
	 * @var array<string, string>
	 */
	protected array $mappingCache = [];

	public function __construct()
	{
		$this->currentMappingKey = $this->generateMappingKey();
	}

	/**
	 * Get current mapping key.
	 */
	public function getCurrentMappingKey(): string
	{
		return $this->currentMappingKey;
	}

	/**
	 * Set mapping key for consistency across operations.
	 */
	public function setMappingKey( string $key ): void
	{
		$this->currentMappingKey = $key;
	}

	/**
	 * Enable or disable mapping storage.
	 */
	public function setStoreMappings( bool $store ): void
	{
		$this->storeMappings = $store;
	}

	/**
	 * Clear all stored mappings (both in-memory and persistent).
	 */
	public function clearMappings(): void
	{
		// Get tracked keys from cache index
		$trackedKeys = Cache::get( self::MAPPING_INDEX_KEY, [] );
		$trackedKeys = is_array( $trackedKeys ) ? $trackedKeys : [];

		// Delete all tracked persistent mappings
		foreach ( $trackedKeys as $cacheKey ) {
			Cache::forget( $cacheKey );
		}

		// Clear the index itself
		Cache::forget( self::MAPPING_INDEX_KEY );

		// Clear in-memory cache
		$this->mappingCache = [];
		// Note: This doesn't clear the Cache store
		// Implement cache clearing logic based on your cache driver
	}

	/**
	 * Check if a pseudonym exists.
	 */
	public function exists( string $pseudonym, string $field ): bool
	{
		return null !== $this->dePseudonymize( $pseudonym, $field );
	}

	/**
	 * Reverse pseudonymization.
	 */
	public function dePseudonymize( string $pseudonym, string $field ): ?string
	{
		$cacheKey = $this->getMappingCacheKey( $pseudonym, $field );

		if ( isset( $this->mappingCache[ $cacheKey ] ) ) {
			return $this->mappingCache[ $cacheKey ];
		}

		$value = Cache::get( $cacheKey );

		if ( null !== $value ) {
			$this->mappingCache[ $cacheKey ] = $value;
		}

		return $value;
	}

	/**
	 * Create a pseudonym token (shorter, for display).
	 */
	public function createToken( string $value, string $field ): string
	{
		$pseudonym = $this->pseudonymize( $value, $field );

		// Return first 12 characters for display purposes
		return substr( $pseudonym, 0, 12 );
	}

	/**
	 * Pseudonymize a value.
	 */
	public function pseudonymize( string $value, string $field ): string
	{
		$salt      = $this->getSalt( $field );
		$pseudonym = $this->generatePseudonym( $value, $salt );

		if ( $this->storeMappings ) {
			$this->storeMapping( $pseudonym, $value, $field );
		}

		return $pseudonym;
	}

	/**
	 * Batch pseudonymize multiple values.
	 *
	 * @param array<string, mixed> $data
	 * @param array<string>        $fields
	 *
	 * @return array<string, mixed>
	 */
	public function batchPseudonymize( array $data, array $fields ): array
	{
		foreach ( $fields as $field ) {
			if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$data[ $field ] = $this->pseudonymize( $data[ $field ], $field );
			}
		}

		return $data;
	}

	/**
	 * Batch de-pseudonymize multiple values.
	 *
	 * @param array<string, mixed> $data
	 * @param array<string>        $fields
	 *
	 * @return array<string, mixed>
	 */
	public function batchDePseudonymize( array $data, array $fields ): array
	{
		foreach ( $fields as $field ) {
			if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$original = $this->dePseudonymize( $data[ $field ], $field );
				if ( null !== $original ) {
					$data[ $field ] = $original;
				}
			}
		}

		return $data;
	}

	/**
	 * Generate a new mapping key.
	 */
	protected function generateMappingKey(): string
	{
		return Str::random( 32 );
	}

	/**
	 * Get cache key for a mapping.
	 */
	protected function getMappingCacheKey( string $pseudonym, string $field ): string
	{
		return 'pseudonym_mapping:' . $field . ':' . $pseudonym;
	}

	/**
	 * Get salt for a field.
	 */
	protected function getSalt( string $field ): string
	{
		// Combine app key with field and mapping key for consistent pseudonyms
		return config( 'app.key' ) . $field . $this->currentMappingKey;
	}

	/**
	 * Generate a consistent pseudonym for a value.
	 *
	 * Note: All algorithms must be deterministic to ensure the same input
	 * always produces the same pseudonym. bcrypt is not supported as it
	 * is non-deterministic by design.
	 */
	protected function generatePseudonym( string $value, string $salt ): string
	{
		$algorithm = config( 'artisanpack.compliance.minimization.anonymization_algorithm', 'sha256' );

		return match ( $algorithm ) {
			'sha256' => hash_hmac( 'sha256', $value, $salt ),
			'sha384' => hash_hmac( 'sha384', $value, $salt ),
			'sha512' => hash_hmac( 'sha512', $value, $salt ),
			default  => hash_hmac( 'sha256', $value, $salt ),
		};
	}

	/**
	 * Store a pseudonym mapping.
	 */
	protected function storeMapping( string $pseudonym, string $value, string $field ): void
	{
		$cacheKey = $this->getMappingCacheKey( $pseudonym, $field );

		// Store in local cache
		$this->mappingCache[ $cacheKey ] = $value;

		// Store in persistent cache (with no expiration by default)
		Cache::put( $cacheKey, $value );

		// Track this key in our index for later cleanup
		$this->trackMappingKey( $cacheKey );
	}

	/**
	 * Track a mapping key in the index for cleanup purposes.
	 */
	protected function trackMappingKey( string $cacheKey ): void
	{
		$trackedKeys = Cache::get( self::MAPPING_INDEX_KEY, [] );
		$trackedKeys = is_array( $trackedKeys ) ? $trackedKeys : [];
		$ttl         = config( 'artisanpack.compliance.minimization.mapping_ttl' );

		if ( ! in_array( $cacheKey, $trackedKeys ) ) {
			$trackedKeys[] = $cacheKey;
			Cache::put( self::MAPPING_INDEX_KEY, $trackedKeys, $ttl );
		}
	}
}
