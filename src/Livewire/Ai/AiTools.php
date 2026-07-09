<?php

/**
 * Livewire trigger surface for the compliance AI features.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Livewire\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Compliance\Ai\Agents\ConsentTextSuggestionAgent;
use ArtisanPackUI\Compliance\Ai\Agents\DpiaAssistanceAgent;
use ArtisanPackUI\Compliance\Ai\Agents\PrivacyPolicyDraftAgent;
use ArtisanPackUI\Compliance\ComplianceServiceProvider;
use ArtisanPackUI\Compliance\Models\AiDraft;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Thin Livewire wrapper for the compliance high-stakes AI agents.
 * Emits browser events the host admin surface listens for and folds
 * into the UI.
 *
 * The component is intentionally transport-only — it holds no
 * persistent state and does not render any UI beyond a marker div.
 * Hosts render:
 * 1. The un-dismissable "requires legal review" banner
 * 2. The acknowledgement checkbox that gates viewing the draft
 * 3. The "Save as new draft" action, which dispatches a
 *    `compliance-ai:save-draft` browser event that this component
 *    persists via {@see AiDraft::create()}. Drafts are append-only —
 *    every save writes a new row.
 *
 * React and Vue front-ends do NOT need this component — they hit the
 * REST endpoints registered under `/api/v1/compliance/ai/*` directly.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */
class AiTools extends Component
{
    /**
     * Draft a starter privacy policy.
     *
     * @param  array<string, mixed>  $payload  Agent input (see PrivacyPolicyDraftAgent docblock).
     */
    #[On( 'compliance-ai:draft-privacy-policy' )]
    public function draftPrivacyPolicy( array $payload ): void
    {
        $this->run(
            'compliance.privacy_policy_draft',
            fn () => PrivacyPolicyDraftAgent::for( $payload )->run(),
        );
    }

    /**
     * Draft a DPIA.
     *
     * @param  array<string, mixed>  $payload  Agent input (see DpiaAssistanceAgent docblock).
     */
    #[On( 'compliance-ai:draft-dpia' )]
    public function draftDpia( array $payload ): void
    {
        $this->run(
            'compliance.dpia_assistance',
            fn () => DpiaAssistanceAgent::for( $payload )->run(),
        );
    }

    /**
     * Suggest consent text.
     *
     * @param  array<string, mixed>  $payload  Agent input (see ConsentTextSuggestionAgent docblock).
     */
    #[On( 'compliance-ai:suggest-consent-text' )]
    public function suggestConsentText( array $payload ): void
    {
        $this->run(
            'compliance.consent_text',
            fn () => ConsentTextSuggestionAgent::for( $payload )->run(),
        );
    }

    /**
     * Persist an AI-generated draft.
     *
     * Append-only: every call creates a new row. The database model
     * throws if anything tries to update an existing draft — the host
     * legal-review workflow depends on the version history being
     * immutable.
     *
     * `metadata.acknowledged` MUST be true — the host is asserting the
     * reviewer checked the "requires legal review" acknowledgement
     * checkbox before saving.
     *
     * @param  string               $featureKey  Feature key that produced the draft.
     * @param  array<string, mixed> $output      Agent output body (the `output` value from a successful run).
     * @param  string|null          $subjectKey  Optional grouping key for related drafts (e.g. document slug).
     * @param  array<string, mixed> $metadata    Free-form metadata; must include `acknowledged => true`.
     */
    #[On( 'compliance-ai:save-draft' )]
    public function saveDraft(
        string $featureKey,
        array $output,
        ?string $subjectKey = null,
        array $metadata = [],
    ): void {
        if ( ! in_array( $featureKey, ComplianceServiceProvider::AI_FEATURE_KEYS, true ) ) {
            $this->dispatch(
                'compliance-ai:save-draft:rejected',
                feature: $featureKey,
                message: 'Unknown compliance AI feature key.',
            );
            return;
        }

        if ( ! Gate::allows( 'manageComplianceAiDrafts' ) ) {
            $this->dispatch(
                'compliance-ai:save-draft:rejected',
                feature: $featureKey,
                message: 'You are not authorized to save compliance AI drafts.',
            );
            return;
        }

        /** @var FeatureRegistry $registry */
        $registry = app( FeatureRegistry::class );

        if ( null === $registry->get( $featureKey ) || ! $registry->isToggleOn( $featureKey ) ) {
            $this->dispatch(
                'compliance-ai:save-draft:rejected',
                feature: $featureKey,
                message: 'This compliance AI feature is currently disabled.',
            );
            return;
        }

        if ( true !== ( $metadata['acknowledged'] ?? false ) ) {
            $this->dispatch(
                'compliance-ai:save-draft:rejected',
                feature: $featureKey,
                message: 'Legal-review acknowledgement is required before a draft can be saved.',
            );
            return;
        }

        try {
            $draft = AiDraft::create( [
                'feature_key' => $featureKey,
                'subject_key' => $subjectKey,
                'content'     => $output,
                'metadata'    => $metadata,
                'created_by'  => auth()->id(),
            ] );

            $this->dispatch(
                'compliance-ai:save-draft:success',
                feature: $featureKey,
                draft_id: $draft->id,
            );
        } catch ( Throwable $e ) {
            Log::error( 'compliance AI draft save failed', [
                'feature' => $featureKey,
                'error'   => $e->getMessage(),
            ] );

            $this->dispatch(
                'compliance-ai:save-draft:error',
                feature: $featureKey,
                message: 'Unexpected error saving draft.',
            );
        }
    }

    /**
     * Return the enabled state of the three compliance.* features.
     *
     * @return array<string, bool>
     */
    public function enabledFeatures(): array
    {
        /** @var FeatureRegistry $registry */
        $registry = app( FeatureRegistry::class );

        $state = [];
        foreach ( ComplianceServiceProvider::AI_FEATURE_KEYS as $key ) {
            $state[ $key ] = null !== $registry->get( $key ) && $registry->isToggleOn( $key );
        }
        return $state;
    }

    /**
     * Marker div only — this is a transport component, not a UI.
     */
    public function render(): string
    {
        return '<div class="ap-compliance-ai-tools" data-testid="ap-compliance-ai-tools"></div>';
    }

    /**
     * Shared agent-run path.
     */
    private function run( string $featureKey, callable $callback ): void
    {
        try {
            $output = $callback();

            $this->dispatch(
                sprintf( 'compliance-ai:%s:success', $featureKey ),
                feature: $featureKey,
                output: $output,
            );
        } catch ( FeatureDisabledException $e ) {
            $this->dispatch(
                sprintf( 'compliance-ai:%s:disabled', $featureKey ),
                feature: $featureKey,
                message: $e->getMessage(),
            );
        } catch ( MissingCredentialsException $e ) {
            $this->dispatch(
                sprintf( 'compliance-ai:%s:missing-credentials', $featureKey ),
                feature: $featureKey,
                message: $e->getMessage(),
            );
        } catch ( FeatureError $e ) {
            $this->dispatch(
                sprintf( 'compliance-ai:%s:invalid-input', $featureKey ),
                feature: $featureKey,
                message: $e->getMessage(),
            );
        } catch ( Throwable $e ) {
            Log::error( 'compliance AI trigger failed', [
                'feature' => $featureKey,
                'error'   => $e->getMessage(),
            ] );

            $this->dispatch(
                sprintf( 'compliance-ai:%s:error', $featureKey ),
                feature: $featureKey,
                message: 'Unexpected error running AI feature.',
            );
        }
    }
}
