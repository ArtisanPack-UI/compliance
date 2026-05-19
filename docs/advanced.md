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
