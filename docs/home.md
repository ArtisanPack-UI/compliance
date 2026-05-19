# ArtisanPack UI — Compliance

A Laravel package that wraps the GDPR / CCPA / LGPD compliance surface in a set of pluggable services, Eloquent models, console commands, and HTTP controllers. Designed for teams that need a single auditable home for consent, data subject rights, processing activities, retention, and reporting — without locking you into a fixed vendor schema.

## Where to start

- New to the package? Start with [Getting Started](getting-started.md) for a 5-minute orientation.
- Setting it up for the first time? See [Installation](installation.md).
- Integrating with your app? See [Usage](usage.md) for the public-facing flows: consent, erasure, portability, monitoring.
- Building custom checks, handlers, or report types? See [Advanced](advanced.md).
- Stuck? See [Troubleshooting](troubleshooting.md) and [FAQ](faq.md).

## Architecture at a glance

The package is organized into seven domains:

| Domain | Public entry | Persistence |
|---|---|---|
| Consent | `ConsentManager`, `ConsentPolicyService` | `consent_policies`, `consent_records`, `consent_audit_logs` |
| Erasure | `ErasureService` + `ErasureHandlerInterface` | `erasure_requests`, `erasure_logs` |
| Portability | `PortabilityService` + `DataExporterInterface` | `portability_requests`, `export_schemas` |
| Assessment | `DpiaService`, `ProcessingActivityService` | `processing_activities`, `data_protection_assessments`, `assessment_risks`, `risk_mitigations` |
| Minimization | `DataMinimizerService` + Anonymization / Pseudonymization engines | (none — operates on app data) |
| Retention | `PurgeExpiredData` command + `RetentionPolicy` / `CollectionPolicy` | `retention_policies`, `collection_policies` |
| Monitoring + reporting | `ComplianceMonitor` + `ComplianceCheckInterface`; `ReportGenerator` + `ReportTypeInterface` | `compliance_check_results`, `compliance_violations`, `compliance_scores`, `scheduled_compliance_reports` |

Every domain follows the same pattern: a thin orchestrator service that delegates to small, interface-bound implementations. You can plug in your own implementation of any interface and the rest of the system picks it up via Laravel's container.

## Where this package fits in the ArtisanPack UI ecosystem

`artisanpack-ui/compliance` is the privacy / regulatory companion to the core security suite. It pairs naturally with:

- [`artisanpack-ui/security`](https://github.com/ArtisanPack-UI/security) — input sanitization, CSP, security headers
- [`artisanpack-ui/rbac`](https://github.com/ArtisanPack-UI/rbac) — gate compliance dashboard access by role
- [`artisanpack-ui/security-analytics`](https://github.com/ArtisanPack-UI/security-analytics) — correlate compliance violations with security events

If you want the full set in one require, install [`artisanpack-ui/security-full`](https://github.com/ArtisanPack-UI/security-full).
