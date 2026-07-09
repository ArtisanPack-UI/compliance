# Advanced

The compliance package is built around small interface contracts so that organizations can plug in their own implementations without forking. Every orchestrator service iterates over container-registered implementations of its corresponding interface.

## Custom erasure handlers

Implement `ErasureHandlerInterface`:

```php
use ArtisanPackUI\Compliance\Compliance\Contracts\ErasureHandlerInterface;
use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureHandlerResult;
use Illuminate\Support\Collection;

class OrderHistoryHandler implements ErasureHandlerInterface
{
    public function getName(): string
    {
        return 'orders';
    }

    public function getDescription(): string
    {
        return 'Removes the user\'s order history from the local store.';
    }

    public function canHandle( int $userId ): bool
    {
        return \App\Models\Order::where( 'user_id', $userId )->exists();
    }

    public function findUserData( int $userId ): Collection
    {
        return \App\Models\Order::where( 'user_id', $userId )->get();
    }

    public function erase( int $userId, array $options = [] ): ErasureHandlerResult
    {
        $count = \App\Models\Order::where( 'user_id', $userId )->delete();

        return new ErasureHandlerResult(
            status:         'success',
            recordsFound:   $count,
            recordsErased:  $count,
        );
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function rollback( int $userId, array $backupData ): bool
    {
        return false;
    }

    public function getEstimatedTime(): int
    {
        return 5;
    }

    public function getDataCategories(): array
    {
        return ['orders', 'transactional'];
    }
}
```

Register it in your service provider:

```php
$this->app->tag( OrderHistoryHandler::class, 'compliance.erasure_handlers' );
```

`ErasureService` resolves all tagged implementations at runtime.

## Custom data exporters

Implement `DataExporterInterface` to surface a data store in portability exports:

```php
use ArtisanPackUI\Compliance\Compliance\Contracts\DataExporterInterface;
use Illuminate\Support\Collection;

class OrderHistoryExporter implements DataExporterInterface
{
    public function getName(): string
    {
        return 'orders';
    }

    public function getCategory(): string
    {
        return 'transactional';
    }

    public function getData( int $userId ): Collection
    {
        return \App\Models\Order::where( 'user_id', $userId )->get();
    }

    public function getSchema(): array
    {
        return [
            'id'         => 'integer',
            'created_at' => 'iso8601',
            'total'      => 'decimal',
        ];
    }

    public function transform( Collection $data ): array
    {
        return $data->map( fn ( $order ) => [
            'id'         => $order->id,
            'created_at' => $order->created_at->toIso8601String(),
            'total'      => (string) $order->total,
        ] )->all();
    }

    public function getSupportedFormats(): array
    {
        return ['json', 'csv'];
    }

    public function getRecordCount( int $userId ): int
    {
        return \App\Models\Order::where( 'user_id', $userId )->count();
    }
}
```

Tag it the same way:

```php
$this->app->tag( OrderHistoryExporter::class, 'compliance.data_exporters' );
```

## Custom compliance checks

Implement `ComplianceCheckInterface`:

```php
use ArtisanPackUI\Compliance\Compliance\Contracts\ComplianceCheckInterface;
use ArtisanPackUI\Compliance\Compliance\Monitoring\CheckResult;

class ConsentExpiryCheck implements ComplianceCheckInterface
{
    public function getName(): string
    {
        return 'consent.expiry';
    }

    public function getDescription(): string
    {
        return 'Flags consent records past their expiry date that have not been renewed.';
    }

    public function getCategory(): string
    {
        return 'consent';
    }

    public function getRegulations(): array
    {
        return ['gdpr'];
    }

    public function run(): CheckResult
    {
        // …
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getRecommendedSchedule(): string
    {
        return 'daily';
    }

    public function getSeverity(): string
    {
        return 'medium';
    }

    public function getRemediation(): string
    {
        return 'Trigger a re-consent flow for affected users.';
    }
}
```

Tag with `compliance.checks`.

## Custom report types

Implement `ReportTypeInterface`, tag with `compliance.report_types`, and reference it by name in `compliance:generate-report --type=…` or in a `ScheduledComplianceReport` row.

## Custom consent storage

Implement `ConsentStorageInterface` and bind it in your service provider:

```php
$this->app->bind(
    \ArtisanPackUI\Compliance\Compliance\Contracts\ConsentStorageInterface::class,
    \App\Compliance\AlternativeConsentStorage::class,
);
```

`ConsentManager` resolves the interface from the container, so the override flows everywhere.

## Compliance dashboard

The package ships a default-deny Gate so that `ComplianceDashboardController` is locked down out of the box. Override in your `AuthServiceProvider`:

```php
Gate::define( 'viewComplianceDashboard', fn ( $user ) => $user->hasRole( 'compliance-officer' ) );
```

When pairing with `artisanpack-ui/rbac`, the Gate integration ensures `viewComplianceDashboard` resolves against an RBAC permission of the same slug if one exists.

## AI features

*Added in 1.1.0. Requires [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai). See [Usage → AI features](usage.md#ai-features) for the surface reference; this section covers customization.*

### Authorize draft persistence

Draft writes go through a `manageComplianceAiDrafts` Gate. The package registers a default-deny stub — you must override it before any user can save. In your `AuthServiceProvider`:

```php
Gate::define( 'manageComplianceAiDrafts', function ( $user ) {
    return $user->hasAnyRole( [ 'compliance-officer', 'dpo' ] );
} );
```

### Change the REST guard

The `/api/v1/compliance/ai/*` routes read the guard from `artisanpack.compliance.ai.guard` (default `sanctum`). For a Livewire-only app that doesn't ship Sanctum:

```php
// config/artisanpack/compliance.php
'ai' => [
    'guard' => env( 'COMPLIANCE_AI_GUARD', 'web' ),
],
```

### Customize the AI feature registry

The three compliance agents are auto-discovered by `artisanpack-ui/ai`'s `FeatureRegistry` from `ComplianceServiceProvider::aiFeatures()`. You can toggle them at runtime through the registry:

```php
use ArtisanPackUI\Ai\Contracts\FeatureRegistry;

app( FeatureRegistry::class )->disable( 'compliance.privacy_policy_draft' );
```

Or via config in `config/artisanpack/ai.php`:

```php
'features' => [
    'compliance.privacy_policy_draft' => [ 'enabled' => true, 'model' => 'claude-sonnet-4-6' ],
],
```

The `model` override is useful for smoke-testing without paying Opus rates.

### Draft retention

`AiDraft` rows are append-only at the ORM layer — this is intentional so version history is preserved for legal review. If your organization needs a formal retention window for AI-generated drafts, add a scheduled job that runs `AiDraft::where('created_at', '<', now()->subMonths(24))->delete()` and logs to your audit trail. Hard-delete is the only path in `1.1.0`; there is no `SoftDeletes` column.

### Consuming events on the browser

The Livewire component dispatches five event categories per feature:

- `compliance-ai:{featureKey}:success` — with `output` payload
- `compliance-ai:{featureKey}:disabled` — feature toggled off
- `compliance-ai:{featureKey}:missing-credentials` — provider credentials not set
- `compliance-ai:{featureKey}:invalid-input` — agent rejected the payload (validation)
- `compliance-ai:{featureKey}:error` — unexpected error

Save-draft events are dispatched under the fixed names `compliance-ai:save-draft:success`, `compliance-ai:save-draft:rejected`, and `compliance-ai:save-draft:error` — the rejected event carries a `message` field naming which guard failed (unknown feature, gate deny, toggle off, missing acknowledgement).

Prefer the JS `Livewire.on()` subscription over inline `x-on:` — event names contain `.` (from the feature key) which Alpine parses as directive modifiers.
