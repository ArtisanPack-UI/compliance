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
use Illuminate\Support\Str;

$request = ErasureRequest::create( [
    'request_number' => 'ER-' . (string) Str::ulid(),
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
use Illuminate\Support\Str;

$request = PortabilityRequest::create( [
    'request_number' => 'PR-' . (string) Str::ulid(),
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

## AI features

*Added in 1.1.0. Requires [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai). See [Installation](installation.md#enable-the-ai-features-optional) for setup.*

The compliance package ships three optional AI trigger surfaces. Every output from every agent is **high-stakes** — the outputs must be reviewed by qualified legal counsel before publication. The package enforces this at three layers so no single caller can bypass it:

- Every agent's output carries `requires_legal_review: true` and a non-empty `review_checklist` (the validators overwrite the model if it tries to omit them).
- The `AiTools::saveDraft` Livewire handler requires the `manageComplianceAiDrafts` Gate to allow, the FeatureRegistry to have the feature toggled on, AND the payload to include `metadata.acknowledged === true`.
- The `AiDraft` model is append-only — any UPDATE attempt throws, so the version history is preserved for legal review.

### Agents

| Feature key | Agent | Default model | Purpose |
|---|---|---|---|
| `compliance.privacy_policy_draft` | `PrivacyPolicyDraftAgent` | `claude-opus-4-7` | Draft a starter privacy policy from declared processing activities. |
| `compliance.dpia_assistance` | `DpiaAssistanceAgent` | `claude-opus-4-7` | Enumerate risks, mitigations, and stakeholder impacts for a DPIA. Enforces that every risk has a matching mitigation. |
| `compliance.consent_text` | `ConsentTextSuggestionAgent` | `claude-sonnet-4-6` | Suggest plain-language consent text with a reading-level score and jurisdiction notes. |

### Trigger from Livewire

Mount the transport component on any admin page:

```blade
<livewire:ap-compliance-ai-tools />
```

Dispatch an event to run an agent, then listen for the result on the browser side:

```blade
<button
    wire:click="$dispatch('compliance-ai:draft-privacy-policy', {
        payload: {
            organization_name: 'Acme',
            contact_email: 'privacy@acme.example',
            effective_date: '2026-08-01',
            jurisdictions: ['EU', 'US-CA'],
            processing_activities: [
                { purpose: 'Order fulfillment', data_categories: ['name','address'], legal_basis: 'contract', retention: '5 years' },
            ],
        }
    })"
>Draft privacy policy</button>

<script>
Livewire.on('compliance-ai:compliance.privacy_policy_draft:success', ({ output }) => {
    // 1. Render the un-dismissable "requires legal review" banner.
    // 2. Render the acknowledgement checkbox.
    // 3. Only reveal output.policy_markdown after the user acknowledges.
});
</script>
```

Save a draft after the reviewer has acknowledged:

```js
Livewire.dispatch('compliance-ai:save-draft', {
    featureKey: 'compliance.privacy_policy_draft',
    output: agentOutput,
    subjectKey: 'main-site',
    metadata: { acknowledged: true, reviewer: 'jane@acme.example' },
});
```

The handler emits `compliance-ai:save-draft:rejected` (with a `message`) if the Gate denies, the toggle is off, or the acknowledgement is missing; `compliance-ai:save-draft:success` (with `draft_id`) otherwise.

### Trigger from React / Vue

Hit the REST endpoints directly. They mount at `/api/v1/compliance/ai/*` under the `api` middleware group, guarded by the guard read from `config('artisanpack.compliance.ai.guard')` (default `sanctum`, override via `COMPLIANCE_AI_GUARD`):

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/v1/compliance/ai/features` | GET | Feature-toggle state map. |
| `/api/v1/compliance/ai/privacy-policy-draft` | POST | Run `PrivacyPolicyDraftAgent`. |
| `/api/v1/compliance/ai/dpia-assistance` | POST | Run `DpiaAssistanceAgent`. |
| `/api/v1/compliance/ai/consent-text` | POST | Run `ConsentTextSuggestionAgent`. |

Every response has the shape `{ feature: string, output: object }` on success, or `{ feature: string, error: string, message: string }` on failure. Error codes: `feature_disabled` (403), `missing_credentials` (503), `invalid_input` (422), `internal_error` (500).

### Retrieve draft history

Every save creates a new `AiDraft` row — the model blocks UPDATE at the ORM layer so the audit trail is immutable. Query it with the shipped scopes:

```php
use ArtisanPackUI\Compliance\Models\AiDraft;

$history = AiDraft::query()
    ->forFeature( 'compliance.privacy_policy_draft' )
    ->forSubject( 'main-site' )
    ->latest( 'created_at' )
    ->get();
```

## Configuration

The published config at `config/artisanpack/compliance.php` exposes:

- Per-domain `enabled` toggles (consent, erasure, portability, monitoring, minimization)
- Route prefix and middleware stack for the HTTP controllers
- Retention defaults (deletion strategy, notification window)
- Report defaults (format, recipient list)
- Storage disk for portability exports
- Per-command per-run limits
- `ai.guard` — auth guard used for the `/api/v1/compliance/ai/*` REST routes (default `sanctum`)

Refer to the file inline comments for the full option set.
