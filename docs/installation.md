# Installation

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- A database supported by Eloquent — SQLite, MySQL, PostgreSQL, SQL Server. The schema uses standard column types (string / text / json / decimal / timestamp / boolean) so any current Laravel-supported driver works.

## Install via Composer

```bash
composer require artisanpack-ui/compliance
```

The service provider auto-registers — no manual `config/app.php` edits required.

## Run the migrations

```bash
php artisan migrate
```

This creates 18 tables:

- `consent_policies`, `consent_records`, `consent_audit_logs`
- `processing_activities`, `data_protection_assessments`, `assessment_risks`, `risk_mitigations`
- `erasure_requests`, `erasure_logs`
- `portability_requests`, `export_schemas`
- `retention_policies`, `collection_policies`
- `compliance_violations`, `compliance_check_results`, `compliance_scores`
- `scheduled_compliance_reports`

If you want to inspect or customize the migrations before running them, publish first:

```bash
php artisan vendor:publish --tag=compliance-migrations
```

## Publish the config (optional)

```bash
php artisan vendor:publish --tag=compliance-config
```

The published config lives at `config/artisanpack/compliance.php`. See [Configuration](usage.md#configuration) for the option set.

## Verify the install

Run the package's tests against an in-memory SQLite instance:

```bash
composer test
```

Or in your own app, smoke-test that the package resolves:

```bash
php artisan tinker --execute='dd(app(\ArtisanPackUI\Compliance\Compliance::class)->version());'
```
