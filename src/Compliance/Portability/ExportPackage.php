<?php

/**
 * ExportPackage component of the Compliance package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Portability;

class ExportPackage
{
    public function __construct(
        protected string $content,
        protected string $format,
        protected string $extension,
        protected string $hash,
        protected int $size,
        protected ?int $userId = null,
    ) {
    }

    /**
     * Get the package content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the format.
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Get the file extension.
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * Get the content hash.
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Get the content size.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Verify content integrity.
     */
    public function verify(): bool
    {
        return hash_equals( $this->hash, hash( 'sha256', $this->content ) );
    }

    /**
     * Get the user ID who requested the export.
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }
}
