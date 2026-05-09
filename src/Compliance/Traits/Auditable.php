<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait Auditable
{
    /**
     * Get the audit trail for this record.
     */
    public function getAuditTrail(): Collection
    {
        // This would typically query an audit log table
        // Returning empty collection as a placeholder
        return collect();
    }

    /**
     * Log a custom audit event.
     *
     * @param  array<string, mixed>  $context
     */
    public function audit( string $action, array $context = [] ): void
    {
        $this->logAuditEvent( $action, $context );
    }

    /**
     * Boot the Auditable trait.
     */
    protected static function bootAuditable(): void
    {
        static::created( function ( $model ): void {
            $model->logAuditEvent( 'created' );
        } );

        static::updated( function ( $model ): void {
            $model->logAuditEvent( 'updated', $model->getDirty(), $model->getOriginal() );
        } );

        static::deleted( function ( $model ): void {
            $model->logAuditEvent( 'deleted' );
        } );
    }

    /**
     * Get the audit log channel.
     */
    protected function getAuditLogChannel(): string
    {
        return $this->auditLogChannel ?? 'audit';
    }

    /**
     * Get attributes to exclude from audit logging.
     *
     * @return array<string>
     */
    protected function getAuditExcludedAttributes(): array
    {
        return $this->auditExcluded ?? ['password', 'remember_token'];
    }

    /**
     * Log an audit event.
     *
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     */
    protected function logAuditEvent( string $event, array $changes = [], array $original = [] ): void
    {
        $excluded = $this->getAuditExcludedAttributes();

        // Filter out excluded attributes
        $changes  = array_diff_key( $changes, array_flip( $excluded ) );
        $original = array_diff_key( $original, array_flip( $excluded ) );

        $data = [
            'model'      => static::class,
            'model_id'   => $this->getKey(),
            'event'      => $event,
            'user_id'    => Auth::id(),
            'user_type'  => Auth::check() ? get_class( Auth::user() ) : null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'timestamp'  => now()->toIso8601String(),
        ];

        if ( ! empty( $changes ) ) {
            $data['changes'] = $changes;
        }

        if ( ! empty( $original ) && 'updated' === $event ) {
            $data['original'] = array_intersect_key( $original, $changes );
        }

        Log::channel( $this->getAuditLogChannel() )->info( "Model {$event}", $data );

        // Store in database if configured
        if ( config( 'artisanpack.compliance.privacy_by_design.audit_trail_enabled', true ) ) {
            $this->storeAuditRecord( $data );
        }
    }

    /**
     * Store audit record in database.
     *
     * @param  array<string, mixed>  $data
     */
    protected function storeAuditRecord( array $data ): void
    {
        // This can be extended to store in a dedicated audit table
        // For now, we rely on the log channel
    }
}
