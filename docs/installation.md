# Installation

## Requirements

- PHP 8.3+
- Laravel 10, 11, 12, or 13
- A database supported by Eloquent — SQLite, MySQL, PostgreSQL, SQL Server. The schema uses standard column types (string / text / json / decimal / timestamp / boolean) so any current Laravel-supported driver works.
- **Optional:** [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai) `^1.0` to enable the AI trigger surfaces (privacy-policy draft, DPIA assistance, consent-text suggestion). The compliance package boots cleanly without it — the AI surfaces stay unregistered.

## Install via Composer

```bash
composer require artisanpack-ui/compliance
```

The service provider auto-registers — no manual `config/app.php` edits required.

## Run the migrations

```bash
php artisan migrate
```

This creates 19 tables:

- `consent_policies`, `consent_records`, `consent_audit_logs`
- `processing_activities`, `data_protection_assessments`, `assessment_risks`, `risk_mitigations`
- `erasure_requests`, `erasure_logs`
- `portability_requests`, `export_schemas`
- `retention_policies`, `collection_policies`
- `compliance_violations`, `compliance_check_results`, `compliance_scores`
- `scheduled_compliance_reports`
- `compliance_ai_drafts` — append-only version history for AI-generated drafts (populated only when the AI features are used)

If you want to inspect or customize the migrations before running them, publish first:

```bash
php artisan vendor:publish --tag=compliance-migrations
```

## Publish the config (optional)

```bash
php artisan vendor:publish --tag=compliance-config
```

The published config lives at `config/artisanpack/compliance.php`. See [Configuration](usage.md#configuration) for the option set.

## Enable the AI features (optional)

If you want the AI trigger surfaces (privacy-policy draft, DPIA assistance, consent-text suggestion), install the AI package and set your provider credentials:

```bash
composer require artisanpack-ui/ai
```

Then toggle the three compliance AI features on in your app config (or in the compliance package's config file after publishing it):

```php
// config/artisanpack/ai.php
'features' => [
    'compliance.privacy_policy_draft' => [ 'enabled' => true ],
    'compliance.dpia_assistance'      => [ 'enabled' => true ],
    'compliance.consent_text'         => [ 'enabled' => true ],
],
```

Register the `manageComplianceAiDrafts` Gate in your `AuthServiceProvider` — the compliance package ships a default-deny stub that must be overridden before any user can save AI-generated drafts:

```php
Gate::define( 'manageComplianceAiDrafts', fn ( $user ) => $user->hasRole( 'compliance-officer' ) );
```

See [Usage → AI features](usage.md#ai-features) for the full surface.

## Verify the install

Run the package's tests against an in-memory SQLite instance:

```bash
composer test
```

Or in your own app, smoke-test that the package resolves:

```bash
php artisan tinker --execute='dd(app(\ArtisanPackUI\Compliance\Compliance::class)->version());'
```
