# ArtisanPack UI — Compliance

Compliance toolkit for Laravel: GDPR / CCPA / LGPD consent management, data subject rights (erasure + portability), DPIA + processing-activity records, data minimization (anonymization + pseudonymization), retention policies, plus compliance monitoring and reporting.

This package is part of the **ArtisanPack UI Security 2.0** split — the privacy / compliance features previously bundled inside `artisanpack-ui/security` 1.x live here in 2.0+.

## Features

- **Consent management** — versioned `ConsentPolicy` per purpose, per-user `ConsentRecord` with status (granted / withdrawn / expired), immutable `ConsentAuditLog`, `ConsentManager` service, `CookieConsentHandler`, `check.consent` middleware
- **Data subject rights**
  - Erasure: `ErasureService` orchestrating pluggable `ErasureHandlerInterface` implementations, with logged outcomes per handler and exemption tracking
  - Portability: `PortabilityService` with pluggable `DataExporterInterface` providers and downloadable JSON / XML / CSV exports
- **DPIA + processing activities** — Article 30 `ProcessingActivity` records, Article 35 `DataProtectionAssessment` with risk + mitigation tracking, `RiskCalculator` for inherent + residual scoring
- **Data minimization** — `AnonymizationEngine`, `PseudonymizationEngine`, `DataMinimizerService`, `data.minimization` middleware
- **Retention policies** — `RetentionPolicy` + `CollectionPolicy` models, `PurgeExpiredData` console command, configurable deletion strategy (delete / anonymize / archive)
- **Compliance monitoring** — `ComplianceMonitor` runs pluggable `ComplianceCheckInterface` implementations, persists `ComplianceCheckResult` rows, raises `ComplianceViolation` records, computes `ComplianceScore` snapshots
- **Reporting** — `ReportGenerator` with pluggable `ReportTypeInterface` providers, `ScheduledComplianceReport` model for cron-driven delivery, multi-format output (PDF / HTML / CSV / JSON)
- **Console commands** — `compliance:run-checks`, `compliance:process-erasure-requests`, `compliance:process-portability-requests`, `compliance:purge-expired-data`, `compliance:generate-report`
- **HTTP controllers** — `ConsentController`, `ErasureController`, `PortabilityController`, `ComplianceDashboardController`
- **Events** — `ConsentGranted`, `ConsentWithdrawn`, `ErasureRequested`, `ErasureCompleted`, `DataExportRequested`, `DataExportCompleted`, `ComplianceCheckCompleted`, `ComplianceViolationDetected` (auto-logged out of the box)

## Installation

```bash
composer require artisanpack-ui/compliance
```

Run the migrations to create the 17 compliance tables:

```bash
php artisan migrate
```

(Optional) Publish the config to override table names, route prefix, deletion strategies, or default formats:

```bash
php artisan vendor:publish --tag=compliance-config
```

## Quick start

### Record consent

```php
use ArtisanPackUI\Compliance\Compliance\Consent\ConsentManager;

$manager = app( ConsentManager::class );
$record  = $manager->grant( $user->id, 'analytics', [
    'collection_method' => 'banner',
    'ip_address'        => request()->ip(),
    'user_agent'        => request()->userAgent(),
] );
```

### Check consent in a route

```php
Route::post( '/track', [TrackingController::class, 'store'] )
    ->middleware( 'check.consent:analytics' );
```

### Process an erasure request

```php
use ArtisanPackUI\Compliance\Models\ErasureRequest;
use ArtisanPackUI\Compliance\Compliance\Erasure\ErasureService;
use Illuminate\Support\Str;

$request = ErasureRequest::create( [
    'request_number' => 'ER-' . (string) Str::ulid(),
    'user_id'        => $user->id,
    'requester_type' => 'self',
    'scope'          => 'full',
    'deadline_at'    => now()->addDays( 30 ),
] );

app( ErasureService::class )->process( $request );
```

### Run compliance checks

```bash
php artisan compliance:run-checks
```

The default service provider listeners log every consent / erasure / data-export / check / violation event to your application log, so you get an out-of-the-box audit trail without wiring anything up yourself.

## Models

| Model | Table | Purpose |
|---|---|---|
| `ConsentPolicy` | `consent_policies` | Versioned legal text + processing details per purpose |
| `ConsentRecord` | `consent_records` | Per-user consent state with policy reference |
| `ConsentAuditLog` | `consent_audit_logs` | Immutable consent change history |
| `ProcessingActivity` | `processing_activities` | GDPR Art. 30 record of processing activities |
| `DataProtectionAssessment` | `data_protection_assessments` | Art. 35 DPIA |
| `AssessmentRisk` | `assessment_risks` | Single risk within a DPIA |
| `RiskMitigation` | `risk_mitigations` | Mitigation measure against a risk |
| `ErasureRequest` | `erasure_requests` | Art. 17 right-to-be-forgotten request |
| `ErasureLog` | `erasure_logs` | Per-handler audit row for an erasure run |
| `PortabilityRequest` | `portability_requests` | Art. 20 data portability request |
| `ExportSchema` | `export_schemas` | Schema definition for portable exports |
| `RetentionPolicy` | `retention_policies` | Rule for how long a category is retained |
| `CollectionPolicy` | `collection_policies` | Rule for what fields may be collected per purpose |
| `ComplianceViolation` | `compliance_violations` | Violation raised by a check |
| `ComplianceCheckResult` | `compliance_check_results` | Single execution of a check |
| `ComplianceScore` | `compliance_scores` | Aggregate compliance posture snapshot |
| `ScheduledComplianceReport` | `scheduled_compliance_reports` | Cron-driven recurring report |

## Extending

The package ships pluggable interfaces so you can add organization-specific behaviour without forking:

- `ComplianceCheckInterface` — define new automated compliance checks
- `ErasureHandlerInterface` — wipe a custom data store as part of erasure
- `DataExporterInterface` — surface a custom data source in portability exports
- `ReportTypeInterface` — emit a custom compliance report type
- `ConsentStorageInterface` — back consent records with an alternative store

Register implementations in your service provider; the orchestrators discover them via the container.

## Documentation

- [Getting Started](docs/getting-started.md)
- [Installation](docs/installation.md)
- [Usage](docs/usage.md)
- [Advanced](docs/advanced.md)
- [FAQ](docs/faq.md)
- [Troubleshooting](docs/troubleshooting.md)

## Requirements

- PHP 8.2+
- Laravel 10 / 11 / 12

## Sibling packages

| Package | Scope |
|---|---|
| [`artisanpack-ui/security-full`](https://github.com/ArtisanPack-UI/security-full) | Meta-package — pulls in the full security suite (all seven packages below) in a single require |
| [`artisanpack-ui/security`](https://github.com/ArtisanPack-UI/security) | Core: input sanitization, output escaping, KSES, CSP, security headers |
| [`artisanpack-ui/rbac`](https://github.com/ArtisanPack-UI/rbac) | Roles, permissions, hierarchy, Blade directives, Gate integration |
| [`artisanpack-ui/security-auth`](https://github.com/ArtisanPack-UI/security-auth) | 2FA, password complexity, account lockout, sessions |
| [`artisanpack-ui/security-advanced-auth`](https://github.com/ArtisanPack-UI/security-advanced-auth) | WebAuthn, SSO, social login, biometric, device fingerprinting |
| [`artisanpack-ui/secure-uploads`](https://github.com/ArtisanPack-UI/secure-uploads) | File validation, malware scanning, signed-URL serving |
| [`artisanpack-ui/security-analytics`](https://github.com/ArtisanPack-UI/security-analytics) | Event logging, anomaly detection, SIEM, dashboards |

## License

MIT — see [LICENSE](LICENSE).

## Contributing

As an open-source project, this package is open to contributions from anyone. Please [read through the contributing guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.
