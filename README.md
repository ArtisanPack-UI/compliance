# ArtisanPack UI — Compliance

Compliance toolkit for Laravel: GDPR / CCPA / LGPD consent management, data subject rights (erasure + portability), DPIA + processing-activity records, data minimization (anonymization + pseudonymization), retention policies, plus compliance monitoring and reporting.

This package is part of the **ArtisanPack UI Security 2.0** split — the privacy / compliance features previously bundled inside `artisanpack-ui/security` (1.x) live here in 2.0+.

> **Status:** scaffold. Content is being extracted from `artisanpack-ui/security` 1.x in follow-up PRs. See the package roadmap on the issue tracker.

## Installation

```bash
composer require artisanpack-ui/compliance
```

## Scope

Once content extraction lands, this package will provide:

- Consent management — `ConsentManager`, `ConsentPolicyService`, `CookieConsentHandler`; per-user consent records with audit trail and `check.consent` middleware
- Data subject rights — `ErasureService` (right-to-be-forgotten), `PortabilityService` (data export, JSON/CSV), pluggable handlers and exporters via interfaces
- DPIA + processing activities — `DpiaService`, `ProcessingActivityService`, `RiskCalculator` for Article 35 records and risk scoring
- Data minimization — `AnonymizationEngine`, `PseudonymizationEngine`, `DataMinimizerService`, plus a `data.minimization` middleware
- Retention policies — collection + retention policy models, `PurgeExpiredData` console command
- Compliance monitoring — `ComplianceMonitor` with pluggable checks (consent validity, DPIA completion, DSR timeliness, retention policy)
- Reporting — `ComplianceReportGenerator`, `ComplianceStatusReport`
- Console commands — `RunComplianceChecks`, `ProcessErasureRequests`, `ProcessPortabilityRequests`, `PurgeExpiredData`, `GenerateComplianceReport`
- HTTP controllers — `ConsentController`, `ErasureController`, `PortabilityController`, `ComplianceDashboardController`
- Events — `ConsentGranted`, `ConsentWithdrawn`, `ErasureRequested`, `ErasureCompleted`, `DataExportRequested`, `DataExportCompleted`, `ComplianceCheckCompleted`, `ComplianceViolationDetected`

## Sibling packages

| Package | Scope |
|---|---|
| [`artisanpack-ui/security`](https://github.com/ArtisanPack-UI/security) | Core: input sanitization, output escaping, KSES, CSP, security headers |
| [`artisanpack-ui/security-advanced-auth`](https://github.com/ArtisanPack-UI/security-advanced-auth) | WebAuthn, SSO, social login, biometric, device fingerprinting |
| [`artisanpack-ui/rbac`](https://github.com/ArtisanPack-UI/rbac) | Roles, permissions, hierarchy, Blade directives, Gate integration |
| [`artisanpack-ui/secure-uploads`](https://github.com/ArtisanPack-UI/secure-uploads) | File validation, malware scanning, secure storage |
| [`artisanpack-ui/security-analytics`](https://github.com/ArtisanPack-UI/security-analytics) | Event logging, anomaly detection, SIEM, dashboards |
| [`artisanpack-ui/security-full`](https://github.com/ArtisanPack-UI/security-full) | Meta-package bundling all of the above |

## Contributing

As an open-source project, this package is open to contributions from anyone. Please [read through the contributing guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.
