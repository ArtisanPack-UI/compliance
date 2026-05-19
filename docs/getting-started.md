# Getting Started

This walks through getting from a fresh Laravel app to a working consent flow in about five minutes.

## 1. Install

```bash
composer require artisanpack-ui/compliance
php artisan migrate
```

That's it for setup — the service provider auto-registers via Laravel's package discovery. Eighteen tables are created (consent, erasure, portability, DPIA, retention, monitoring, reporting).

## 2. Define a consent policy

A `ConsentPolicy` is the versioned legal text the user is agreeing to. Create one per purpose:

```php
use ArtisanPackUI\Compliance\Models\ConsentPolicy;

ConsentPolicy::create( [
    'purpose'           => 'analytics',
    'name'              => 'Analytics tracking',
    'description'       => 'We track page views to improve the product.',
    'legal_text'        => 'I consent to anonymous analytics tracking…',
    'version'           => '1.0',
    'is_required'       => false,
    'is_active'         => true,
    'requires_explicit' => true,
    'minimum_age'       => 16,
    'effective_at'      => now(),
] );
```

## 3. Record consent

When the user agrees, record it:

```php
use ArtisanPackUI\Compliance\Compliance\Consent\ConsentManager;

$manager = app( ConsentManager::class );

$record = $manager->grant( $user->id, 'analytics', [
    'collection_method' => 'banner',
    'ip_address'        => request()->ip(),
    'user_agent'        => request()->userAgent(),
] );
```

The `ConsentGranted` event fires, an audit log row is written, and (if no active record existed) a fresh `ConsentRecord` is persisted.

## 4. Gate a route on it

```php
Route::post( '/track', [TrackingController::class, 'store'] )
    ->middleware( 'check.consent:analytics' );
```

Requests without an active granted-and-unexpired `ConsentRecord` for the `analytics` purpose return a 403.

## 5. Withdraw consent

```php
$manager->withdraw( $user->id, 'analytics', [
    'reason' => 'User opted out from preferences page.',
] );
```

The record's status flips to `withdrawn`, the `ConsentWithdrawn` event fires, and a new audit log row is appended. Downstream listeners (the package ships log-only defaults) can then trigger your own cleanup.

## Next steps

- [Erasure requests](usage.md#erasure-requests)
- [Portability exports](usage.md#portability-exports)
- [Compliance monitoring](usage.md#compliance-monitoring)
- [Writing custom checks / handlers / exporters](advanced.md)
