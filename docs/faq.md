# FAQ

## Why is this a separate package from `artisanpack-ui/security`?

In the 1.x line, compliance lived inside `artisanpack-ui/security`. That bundled too many unrelated concerns (auth, RBAC, file uploads, CSP, GDPR …) into one require. The 2.0 split breaks each concern out into its own focused package; consumers pick what they need. Use the `artisanpack-ui/security-full` meta-package if you want everything in one install.

## Does this package replace my legal review process?

No. The package gives you the data structures and machinery to record consent, run erasure, generate DPIAs, run automated checks, and produce reports. The policies themselves, the legal text users see, and the determination of what counts as a violation are decisions you (or your legal counsel) make. The package keeps the receipts.

## Does it ship erasure handlers for my user data?

The package itself ships no opinionated handlers for application data — only the orchestration. You implement `ErasureHandlerInterface` for each data store you want erased (users table, orders, support tickets, audit logs you've cleared for deletion, etc.) and register them in your service provider. The same pattern applies to portability exporters.

## Why is the compliance dashboard locked by default?

Because letting anyone view DPIAs, consent records, and pending erasure requests is a worse default than locking it down. The service provider registers a `viewComplianceDashboard` Gate that returns `false` unless you override it.

## Does it work with `artisanpack-ui/rbac`?

Yes. The Gate integration in RBAC will resolve `viewComplianceDashboard` against an RBAC permission of the same slug, so you can define it once as a permission and assign it to a role rather than redefining the Gate. The packages are independent — neither requires the other.

## What happens to consent records when a `ConsentPolicy` is updated?

Old `ConsentRecord` rows are not modified automatically. They retain their `policy_version` reference. Whether you treat that as still-valid consent or as needing a re-consent flow is a policy decision; the package gives you the data to make that call.

## Where are exported portability files stored?

On the storage disk configured in `config/artisanpack/compliance.php`. The default is `local`. Use a private S3 bucket (or equivalent) in production — the files contain copies of user data.

## Does the package handle SCC / DPA paperwork?

No — it tracks `ProcessingActivity` records (Article 30 RoPA), which is the precursor / index. It does not generate SCCs or DPA contracts.

## Do I have to install `artisanpack-ui/ai` to use the compliance package?

*Added in 1.1.0.*

No. The AI package is a soft dependency — the compliance package boots and works fully without it. When the AI package is absent, the AI Livewire component and the `/api/v1/compliance/ai/*` REST endpoints simply stay unregistered.

## Is AI-generated output from the agents legal advice?

No. Every high-stakes agent (`PrivacyPolicyDraftAgent`, `DpiaAssistanceAgent`, `ConsentTextSuggestionAgent`) forces `requires_legal_review: true` on its output and includes a `review_checklist` naming things a lawyer must confirm. The host UI is required to render an un-dismissable "requires legal review" banner and gate viewing the draft body behind an acknowledgement checkbox. The package deliberately makes it hard to ship AI output as-is.

## Can I stop a specific AI feature without uninstalling the package?

*Added in 1.1.0.*

Yes — toggle the feature off in the FeatureRegistry (`config/artisanpack/ai.php`):

```php
'features' => [
    'compliance.privacy_policy_draft' => [ 'enabled' => false ],
],
```

The agent will throw `FeatureDisabledException` when run, the REST endpoint returns 403, and `AiTools::saveDraft` rejects saves for that feature key. The `/features` endpoint reports the disabled state so front-ends can hide the affordance.

## An AI-generated draft turned out wrong — can I delete or edit it?

The `AiDraft` model is append-only at the ORM layer — any UPDATE throws a `RuntimeException`. This is deliberate: the version history is legal evidence. To "correct" a bad draft, save a new draft with the corrected content; both remain in the audit trail.

If you need to hard-delete rows for a GDPR erasure cascade, use `AiDraft::query()->where(...)->delete()` and log the deletion event alongside the erasure trail. There is no soft-delete column in `1.1.0` — hard-delete is the only path.
