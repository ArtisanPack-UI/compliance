# ArtisanPack UI — Compliance Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-05-18

### Added

- Initial release of the standalone Compliance package, extracted from `artisanpack-ui/security` 1.x as part of the Security 2.0 package split.
- **Consent management** — versioned `ConsentPolicy` per purpose, per-user `ConsentRecord` with status lifecycle, immutable `ConsentAuditLog`, `ConsentManager` service, `ConsentPolicyService` for version transitions, `CookieConsentHandler`, `check.consent` route middleware.
- **Data subject rights** — `ErasureService` orchestrating pluggable `ErasureHandlerInterface` implementations with logged outcomes per handler and exemption tracking; `PortabilityService` with pluggable `DataExporterInterface` providers and downloadable JSON / XML / CSV exports.
- **DPIA + processing activities** — Article 30 `ProcessingActivity` records, Article 35 `DataProtectionAssessment` with risk + mitigation tracking, `RiskCalculator` for inherent + residual scoring, `DpiaService` for full assessment lifecycle.
- **Data minimization** — `AnonymizationEngine`, `PseudonymizationEngine`, `DataMinimizerService`, `data.minimization` route middleware.
- **Retention policies** — `RetentionPolicy` + `CollectionPolicy` models, `PurgeExpiredData` console command, configurable deletion strategy (delete / anonymize / archive).
- **Compliance monitoring** — `ComplianceMonitor` runs pluggable `ComplianceCheckInterface` implementations, persists `ComplianceCheckResult` rows, raises `ComplianceViolation` records, computes `ComplianceScore` snapshots with letter-grade output.
- **Reporting** — `ReportGenerator` with pluggable `ReportTypeInterface` providers, `ScheduledComplianceReport` model for cron-driven delivery, multi-format output (PDF / HTML / CSV / JSON).
- **17 Eloquent models** — `ConsentPolicy`, `ConsentRecord`, `ConsentAuditLog`, `ProcessingActivity`, `DataProtectionAssessment`, `AssessmentRisk`, `RiskMitigation`, `ErasureRequest`, `ErasureLog`, `PortabilityRequest`, `ExportSchema`, `RetentionPolicy`, `CollectionPolicy`, `ComplianceViolation`, `ComplianceCheckResult`, `ComplianceScore`, `ScheduledComplianceReport`, plus the `PrivacyAwareModel` base class.
- **18 migrations** creating every backing table with foreign keys, indices, and a guarded unique-granted-consent constraint.
- **5 console commands** — `RunComplianceChecks`, `ProcessErasureRequests`, `ProcessPortabilityRequests`, `PurgeExpiredData`, `GenerateComplianceReport`.
- **4 HTTP controllers** — `ConsentController`, `ErasureController`, `PortabilityController`, `ComplianceDashboardController` (gated behind a default-deny `viewComplianceDashboard` Gate).
- **8 events** — `ConsentGranted`, `ConsentWithdrawn`, `ErasureRequested`, `ErasureCompleted`, `DataExportRequested`, `DataExportCompleted`, `ComplianceCheckCompleted`, `ComplianceViolationDetected` — auto-listened by the service provider so apps get an audit trail in the log without wiring anything up.
- **5 contract interfaces** — `ComplianceCheckInterface`, `ConsentStorageInterface`, `DataExporterInterface`, `ErasureHandlerInterface`, `ReportTypeInterface` — for extending the toolkit with organization-specific behaviour.
- **Helper function** `compliance()` plus `Compliance` Facade entry points.
- **PHP-CS-Fixer + PHPCS** code style enforcement matching the ArtisanPack UI ecosystem conventions (WordPress-style spacing, Yoda conditions, aligned operators).

### Notes

The pre-1.0 `0.1.0` scaffold release shipped with import references to model classes that did not yet exist as PHP files (the migrations created the tables, but the Eloquent classes were missing). 1.0.0 ships the full set of 17 model classes and brings the package to a runnable state.
