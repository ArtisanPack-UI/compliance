# Usage

This document covers the public-facing flows: consent, erasure, portability, processing activities, retention, and compliance monitoring.

## Consent management

### Define a policy

A `ConsentPolicy` is the legal text the user is agreeing to. Create one per purpose, with versioning:

```php
use ArtisanPackUI\Compliance\Models\ConsentPolicy;

ConsentPolicy::create( [
    'purpose'        => 'marketing',
    'name'           => 'Marketing emails',
    'legal_text'     => 'I consent to receive marketing emails.',
    'version'        => '1.0',
    'is_required'    => false,
    'is_active'      => true,
    'effective_at'   => now(),
] );
```

When you need to update the legal text, create a new version with `previous_version_id` set; `ConsentPolicy::getLatestForPurpose()` returns the most recently effective active version.

### Record consent

```php
use ArtisanPackUI\Compliance\Compliance\Consent\ConsentManager;

app( ConsentManager::class )->grant( $userId, 'marketing', [
    'collection_method' => 'checkout',
    'ip_address'        => request()->ip(),
    'user_agent'        => request()->userAgent(),
    'granular_choices'  => ['weekly_digest', 'product_updates'],
] );
```

### Withdraw consent

```php
app( ConsentManager::class )->withdraw( $userId, 'marketing', [
    'reason' => 'User unsubscribed via email link.',
] );
```

### Gate routes / actions

```php
Route::post( '/newsletter/subscribe', SubscribeController::class )
    ->middleware( 'check.consent:marketing' );
```

The middleware aborts 403 if there is no active granted-and-unexpired `ConsentRecord` for the purpose.

## Erasure requests

### Create a request

```php
use ArtisanPackUI\Compliance\Models\ErasureRequest;

$request = ErasureRequest::create( [
    'request_number' => 'ER-' . str_pad( (string) ( ErasureRequest::count() + 1 ), 6, '0', STR_PAD_LEFT ),
    'user_id'        => $user->id,
    'requester_type' => 'self',
    'scope'          => 'full',
    'deadline_at'    => now()->addDays( 30 ),
] );
```

### Process it

```php
use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureService;

app( ErasureService::class )->process( $request );
```

The service iterates over every registered `ErasureHandlerInterface` implementation, asks each whether it can handle the user, calls `erase()`, and records the per-handler outcome to `erasure_logs`. See [Advanced — custom erasure handlers](advanced.md#custom-erasure-handlers) to plug in handlers for your own data stores.

### Schedule batch processing

```bash
php artisan compliance:process-erasure-requests
```

The command picks up pending requests, runs them through the service, and stops at the configured per-run limit.

## Portability exports

### Create a request

```php
use ArtisanPackUI\Compliance\Models\PortabilityRequest;

$request = PortabilityRequest::create( [
    'request_number' => 'PR-' . str_pad( (string) ( PortabilityRequest::count() + 1 ), 6, '0', STR_PAD_LEFT ),
    'user_id'        => $user->id,
    'requester_type' => 'self',
    'format'         => 'json',
    'transfer_type'  => 'download',
    'download_limit' => 3,
    'deadline_at'    => now()->addDays( 30 ),
] );
```

### Process it

```php
use ArtisanPackUI\Compliance\Compliance\Portability\PortabilityService;

app( PortabilityService::class )->process( $request );
```

The service iterates over every registered `DataExporterInterface`, collects the user's data, applies optional transformations defined in `ExportSchema`, and writes a file to the configured disk. The model exposes `canDownload()` and `incrementDownloadCount()` for serving the file.

## Processing activities + DPIAs

```php
use ArtisanPackUI\Compliance\Models\ProcessingActivity;

ProcessingActivity::create( [
    'name'              => 'Order fulfilment',
    'purposes'          => ['fulfilment', 'tax_records'],
    'legal_bases'       => ['contract', 'legal_obligation'],
    'data_categories'   => ['contact', 'address', 'payment'],
    'data_subjects'     => ['customers'],
    'recipients'        => ['shipping_provider', 'tax_authority'],
    'security_measures' => ['encryption_at_rest', 'access_control'],
    'dpia_required'     => true,
    'status'            => 'active',
] );
```

Set `dpia_required => true` for high-risk activities, then create a `DataProtectionAssessment` linked to the activity. Risks live in `AssessmentRisk` (with `calculateInherentScore()` and `determineRiskLevel()` helpers); mitigations in `RiskMitigation`.

## Retention

Define a `RetentionPolicy` per data category / model class:

```php
use ArtisanPackUI\Compliance\Models\RetentionPolicy;

RetentionPolicy::create( [
    'name'              => 'Order records — 7 years',
    'model_class'       => App\Models\Order::class,
    'retention_days'    => 365 * 7,
    'deletion_strategy' => 'anonymize',
    'is_active'         => true,
] );
```

Then run the purge command on schedule:

```bash
php artisan compliance:purge-expired-data
```

`CollectionPolicy` covers the inverse — what fields are allowed to be collected for a given purpose — and pairs with the `data.minimization` middleware to enforce inbound limits.

## Compliance monitoring

Register checks (custom or shipped), then run them:

```bash
php artisan compliance:run-checks
```

Each run persists a `ComplianceCheckResult` row, raises `ComplianceViolation` records for failures, and (when run for all checks) writes a `ComplianceScore` snapshot. See [Advanced — custom compliance checks](advanced.md#custom-compliance-checks) for writing your own.

## Reporting

```bash
php artisan compliance:generate-report --type=quarterly --format=pdf
```

`ScheduledComplianceReport` rows drive cron-based recurring delivery. See [Advanced — custom report types](advanced.md#custom-report-types).

## Configuration

The published config at `config/artisanpack/compliance.php` exposes:

- Per-domain `enabled` toggles (consent, erasure, portability, monitoring, minimization)
- Route prefix and middleware stack for the HTTP controllers
- Retention defaults (deletion strategy, notification window)
- Report defaults (format, recipient list)
- Storage disk for portability exports
- Per-command per-run limits

Refer to the file inline comments for the full option set.
