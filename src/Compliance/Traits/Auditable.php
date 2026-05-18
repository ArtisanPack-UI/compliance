<?php

/**
 * Auditable trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait Auditable
{
    /**
     * Get the audit trail for this record.
     *
     * The trait's default audit storage is the configured Laravel log
     * channel (see {@see logAuditEvent}); apps that need a queryable
     * audit trail should override this method *and* {@see storeAuditRecord}
     * together to read from / write to their own audit-record table.
     * Returns an empty collection by default.
     */
    public function getAuditTrail(): Collection
    {
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
        $excluded = $this->auditExcluded ?? ['password', 'remember_token'];

        // When the model also uses PrivacyByDesign, fold its
        // personal/sensitive attribute lists in automatically so PII
        // can't leak into the audit channel by default.
        if ( method_exists( $this, 'getPersonalDataAttributes' ) ) {
            $excluded = array_merge( $excluded, $this->getPersonalDataAttributes() );
        }

        if ( method_exists( $this, 'getSensitiveDataAttributes' ) ) {
            $excluded = array_merge( $excluded, $this->getSensitiveDataAttributes() );
        }

        return array_values( array_unique( $excluded ) );
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
            'model'     => static::class,
            'model_id'  => $this->getKey(),
            'event'     => $event,
            'user_id'   => Auth::id(),
            'user_type' => Auth::check() ? get_class( Auth::user() ) : null,
            'timestamp' => now()->toIso8601String(),
        ];

        // IP / user-agent are personal data under most regimes — keep
        // them off the audit payload by default and let apps opt in
        // when their compliance posture allows it.
        if ( (bool) config( 'artisanpack.compliance.privacy_by_design.audit_include_request_metadata', false ) ) {
            $data['ip_address'] = request()?->ip();
            $data['user_agent'] = request()?->userAgent();
        }

        if ( ! empty( $changes ) ) {
            $data['changes'] = $changes;
        }

        if ( ! empty( $original ) && 'updated' === $event ) {
            $data['original'] = array_intersect_key( $original, $changes );
        }

        // Defer the audit write until the surrounding DB transaction
        // commits so a rolled-back save() doesn't leave a phantom audit
        // entry. Use the model's own connection (not the DB facade /
        // default connection) so multi-database apps tie the callback
        // to the right transaction. afterCommit runs immediately if
        // there's no active transaction on that connection.
        $channel  = $this->getAuditLogChannel();
        $logTrail = (bool) config( 'artisanpack.compliance.privacy_by_design.audit_trail_enabled', true );

        $this->getConnection()->afterCommit( function () use ( $channel, $event, $data, $logTrail ): void {
            Log::channel( $channel )->info( "Model {$event}", $data );

            if ( $logTrail ) {
                $this->storeAuditRecord( $data );
            }
        } );
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
