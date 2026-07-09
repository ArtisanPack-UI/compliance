# ArtisanPack UI — Compliance Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- **Authorization gate on draft persistence** — `AiTools::saveDraft` now checks a `manageComplianceAiDrafts` Gate (default-deny, override in the app's `AuthServiceProvider`) before writing to `compliance_ai_drafts`. Previously any browser session that reached a page mounting the component could persist forged drafts.
- **Configurable auth guard** — the `/api/v1/compliance/ai/*` REST routes now read the guard from `config('artisanpack.compliance.ai.guard')` (default `sanctum`, override via `COMPLIANCE_AI_GUARD`). Consumer apps without Sanctum can point at `web` or any configured guard, replacing the previous hard-coded `auth:sanctum` that 500'd when Sanctum wasn't installed.

### Fixed

- **Feature-toggle bypass on save** — `AiTools::saveDraft` now rejects saves when the feature is toggled OFF in the FeatureRegistry (was only checking `AI_FEATURE_KEYS` membership).
- **DPIA risk↔mitigation linkage** — `DpiaAssistanceAgent::validateOutput` now enforces that every enumerated risk has a matching mitigation entry (linked by `risk_title`). Previously the "every risk must have a mitigation" invariant declared in the prompt was not verified in code, so a partial DPIA could ship as valid output.
- **Privacy-policy input validation** — `PrivacyPolicyDraftAgent` now rejects non-array elements in `processing_activities`, closing a Livewire-only path (REST callers were already covered by the FormRequest) that could send mixed lists to the prompter.
- **Consent-text short label** — `ConsentTextSuggestionAgent` now throws `FeatureError` when the model returns an empty `short_label`. An unlabelled consent checkbox does not meet the "clear affirmative act" standard.

### Added

- **AI integration** — three high-stakes agents powered by `artisanpack-ui/ai` (soft dependency):
  - `compliance.privacy_policy_draft` (`PrivacyPolicyDraftAgent`, default model `claude-opus-4-7`) — drafts a starter privacy policy from declared processing activities.
  - `compliance.dpia_assistance` (`DpiaAssistanceAgent`, default model `claude-opus-4-7`) — enumerates risks, mitigations, and stakeholder impacts for a DPIA.
  - `compliance.consent_text` (`ConsentTextSuggestionAgent`, default model `claude-sonnet-4-6`) — suggests plain-language consent text with reading-level score and jurisdiction notes.
- **Guardrail contract** — every agent forces `requires_legal_review: true` and a non-empty `review_checklist` on its output; the host UI is required to render an un-dismissable "requires legal review" banner and an acknowledgement checkbox before the draft body is shown.
- **Append-only draft storage** — new `compliance_ai_drafts` table + `AiDraft` model preserve every generation as a new row; the model throws on any attempt to update an existing draft.
- **Trigger surfaces** — Livewire component `ap-compliance-ai-tools` for Blade/Livewire hosts, plus `/api/v1/compliance/ai/*` REST endpoints (Sanctum-gated) for React and Vue hosts. Both are skipped when `artisanpack-ui/ai` is not installed. (#18, #19, #20)

## [1.0.1] - 2026-06-14

### Added

- **Laravel 13 support** — widened `illuminate/support` constraint to `^10.0|^11.0|^12.0|^13.0` so consumer apps (and `artisanpack-ui/security-full`, which pulls Compliance in transitively) can adopt Laravel 13. PHP `^8.2` is preserved; Composer's solver picks Laravel 10/11/12 on PHP 8.2 and only picks Laravel 13 on PHP 8.3+. (#15)

### Changed

- **CI matrix** — `Test` job now runs across Laravel 12/13 × PHP 8.2/8.3/8.4 (excluding L13/PHP 8.2, which Laravel 13 itself forbids). Workflow also gained `release/**` triggers and least-privilege `permissions: contents: read` + `persist-credentials: false` hardening.
- **Dev dependency constraints widened to unblock the Laravel 13 leg** —
  - `pestphp/pest` → `^3.8|^4.0`
  - `pestphp/pest-plugin-laravel` → `^3.2|^4.0`
  - `orchestra/testbench` → `^10.2|^11.0`

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
