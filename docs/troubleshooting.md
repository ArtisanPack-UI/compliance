# Troubleshooting

## Class `ArtisanPackUI\Compliance\Models\…` not found

You're running against the pre-1.0 `0.1.0` scaffold release, which shipped imports for model classes that didn't exist as PHP files. Upgrade to `^1.0`:

```bash
composer require artisanpack-ui/compliance:^1.0
composer dump-autoload
```

## `php artisan migrate` fails with "table already exists"

Either you already ran the migrations once (check `php artisan migrate:status`), or you have a competing migration in your app creating one of the same-named tables. The package's tables are:

- `consent_policies`, `consent_records`, `consent_audit_logs`
- `processing_activities`, `data_protection_assessments`, `assessment_risks`, `risk_mitigations`
- `erasure_requests`, `erasure_logs`
- `portability_requests`, `export_schemas`
- `retention_policies`, `collection_policies`
- `compliance_violations`, `compliance_check_results`, `compliance_scores`
- `scheduled_compliance_reports`

If any of those names clash with an app migration, rename one or the other.

## Consent records are silently failing the unique constraint

There is a unique partial index on `(user_id, purpose)` filtered to `status = 'granted'`. You cannot have two simultaneously-granted records for the same user / purpose. `ConsentManager::grant()` handles this for you by withdrawing the previous record first. If you're calling `ConsentRecord::create()` directly, you'll have to do the same.

## `check.consent` middleware always returns 403

Verify:

1. There is an active `ConsentPolicy` for the purpose (`is_active = true`, `effective_at <= now()`).
2. The user has a `ConsentRecord` with `status = 'granted'` and no past `expires_at`.
3. The route is authenticated — middleware short-circuits for unauthenticated requests.

`ConsentRecord::valid()->where('user_id', $user->id)->where('purpose', 'analytics')->exists()` is the same query the middleware runs.

## `compliance:purge-expired-data` deleted records I didn't expect

Audit your `RetentionPolicy` rows — every active policy with a matching `model_class` and elapsed `retention_days` is in scope for the configured `deletion_strategy`. Test policies against a staging copy first; the command does not ship a dry-run flag by default.

## Compliance dashboard returns 403 for an admin user

The package's default Gate is deny-all. Override it in your `AuthServiceProvider`:

```php
Gate::define( 'viewComplianceDashboard', fn ( $user ) => $user->hasRole( 'admin' ) );
```

If using `artisanpack-ui/rbac`, you can instead seed a `viewComplianceDashboard` permission and assign it to the relevant role — RBAC's `Gate::before` hook resolves it without an explicit Gate definition.

## Tests fail with "table 'compliance_…' has no column 'foo'"

You added a column to a fillable list without updating the corresponding migration. The package's own migrations are the source of truth — if you've published them locally and edited, re-run with `migrate:fresh`.
