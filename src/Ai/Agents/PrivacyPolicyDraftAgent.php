<?php

/**
 * Privacy-policy draft agent.
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Ai\Agents;

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Credentials\Credentials;
use ArtisanPackUI\Ai\Exceptions\FeatureError;

/**
 * Generate a starter privacy policy from declared processing activities.
 *
 * **High-stakes agent**: every output carries an un-dismissable
 * `requires_legal_review` marker and a mandatory review checklist. The
 * agent itself never claims regulatory sufficiency — the host UI must
 * gate the draft behind a legal-review acknowledgement and persist
 * every run as a new {@see \ArtisanPackUI\Compliance\Models\AiDraft}
 * row (never overwriting a prior version).
 *
 * ## Input
 *
 * ```
 * [
 *   'organization_name'    => string,     // required
 *   'processing_activities' => list<array{
 *       purpose: string,
 *       data_categories: list<string>,
 *       legal_basis: string,
 *       retention: string,
 *       recipients?: list<string>,
 *   }>,                                    // required, non-empty
 *   'jurisdictions'         => list<string>, // required, non-empty
 *   'contact_email'         => string,     // required
 *   'effective_date'        => string,     // required, YYYY-MM-DD
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   policy_markdown: string,           // full policy body in markdown
 *   section_headings: list<string>,    // top-level H2 headings, in order
 *   requires_legal_review: true,       // always true, never omit
 *   review_checklist: list<string>,    // items a lawyer must confirm
 *   jurisdiction_notes: map<string,string>, // per-jurisdiction caveats
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */
class PrivacyPolicyDraftAgent extends ArtisanPackAgent
{
    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'compliance.privacy_policy_draft';

    /**
     * {@inheritDoc}
     */
    public string $package = 'artisanpack-ui/compliance';

    /**
     * {@inheritDoc}
     */
    public string $defaultModel = 'claude-opus-4-7';

    /**
     * {@inheritDoc}
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You draft a STARTER privacy policy from a set of declared processing activities. You are not a lawyer and the output is not legal advice.

Non-negotiables:
- The output is a starting point that MUST be reviewed by qualified legal counsel before publication.
- Base every claim strictly on the declared processing activities. Do NOT invent categories of data, retention periods, third-party recipients, or legal bases the caller did not supply.
- Use plain, direct language. Prefer concrete sentences over regulatory boilerplate.
- Structure the policy with standard H2 sections: What data we collect, How we use it, Legal basis, Who we share it with, How long we keep it, Your rights, How to contact us. Add jurisdiction-specific sections where relevant (e.g. CCPA "Do Not Sell", GDPR data-subject rights, LGPD holder rights).
- If the caller supplied conflicting or ambiguous data, note the conflict in `review_checklist` rather than resolving it silently.

Return a JSON object with these keys:
- `policy_markdown` (string): the full policy body as GitHub-flavored markdown, starting with a top-level H1 title.
- `section_headings` (list of strings): the H2 headings you used, in document order.
- `requires_legal_review` (boolean): MUST be `true`.
- `review_checklist` (list of strings): specific items a reviewing attorney must verify (e.g. "Confirm the 90-day retention on marketing data is defensible under CCPA §1798.100"). Include at minimum: jurisdiction-specific requirements, retention periods, third-party recipients, and any ambiguities you flagged.
- `jurisdiction_notes` (object mapping jurisdiction code → note string): per-jurisdiction caveats the reviewer should double-check.
PROMPT;
    }

    /**
     * {@inheritDoc}
     */
    public function outputSchema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => [
                'policy_markdown',
                'section_headings',
                'requires_legal_review',
                'review_checklist',
                'jurisdiction_notes',
            ],
            'properties' => [
                'policy_markdown'       => [ 'type' => 'string' ],
                'section_headings'      => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
                'requires_legal_review' => [ 'type' => 'boolean' ],
                'review_checklist'      => [
                    'type'     => 'array',
                    'minItems' => 1,
                    'items'    => [ 'type' => 'string' ],
                ],
                'jurisdiction_notes'    => [
                    'type'                 => 'object',
                    'additionalProperties' => [ 'type' => 'string' ],
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function execute( Credentials $credentials, string $model, string $instructions ): array
    {
        $normalized = $this->normalizeInput( $this->input() );

        $prompter = app( AgentPrompter::class );

        $result = $prompter->prompt(
            credentials: $credentials,
            model: $model,
            instructions: $instructions,
            message: $this->buildMessage( $normalized ),
            outputSchema: $this->outputSchema(),
        );

        return [
            'output'        => $this->validateOutput( $result['output'] ?? [] ),
            'input_tokens'  => (int) ( $result['input_tokens'] ?? 0 ),
            'output_tokens' => (int) ( $result['output_tokens'] ?? 0 ),
        ];
    }

    /**
     * Validate and shape the raw agent input.
     *
     * @return array{
     *     organization_name: string,
     *     processing_activities: list<array<string, mixed>>,
     *     jurisdictions: list<string>,
     *     contact_email: string,
     *     effective_date: string,
     * }
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature( $this->featureKey, 'input must be an array.' );
        }

        $org           = isset( $input['organization_name'] ) && is_string( $input['organization_name'] ) ? trim( $input['organization_name'] ) : '';
        $contact       = isset( $input['contact_email'] ) && is_string( $input['contact_email'] ) ? trim( $input['contact_email'] ) : '';
        $effectiveDate = isset( $input['effective_date'] ) && is_string( $input['effective_date'] ) ? trim( $input['effective_date'] ) : '';
        $activities    = $input['processing_activities'] ?? null;
        $jurisdictions = $input['jurisdictions'] ?? null;

        if ( '' === $org ) {
            throw FeatureError::forFeature( $this->featureKey, '`organization_name` is required.' );
        }

        if ( '' === $contact || false === filter_var( $contact, FILTER_VALIDATE_EMAIL ) ) {
            throw FeatureError::forFeature( $this->featureKey, '`contact_email` must be a valid email address.' );
        }

        if ( '' === $effectiveDate || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $effectiveDate ) ) {
            throw FeatureError::forFeature( $this->featureKey, '`effective_date` must be a YYYY-MM-DD string.' );
        }

        if ( ! is_array( $activities ) || [] === $activities ) {
            throw FeatureError::forFeature( $this->featureKey, '`processing_activities` must be a non-empty array.' );
        }

        foreach ( $activities as $index => $activity ) {
            if ( ! is_array( $activity ) ) {
                throw FeatureError::forFeature(
                    $this->featureKey,
                    sprintf( '`processing_activities[%s]` must be an object with keys purpose, data_categories, legal_basis, retention.', (string) $index ),
                );
            }
        }

        if ( ! is_array( $jurisdictions ) || [] === $jurisdictions ) {
            throw FeatureError::forFeature( $this->featureKey, '`jurisdictions` must be a non-empty array.' );
        }

        return [
            'organization_name'     => $org,
            'processing_activities' => array_values( $activities ),
            'jurisdictions'         => array_values( array_map( 'strval', $jurisdictions ) ),
            'contact_email'         => $contact,
            'effective_date'        => $effectiveDate,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @return array<int, array<string, string>>
     */
    protected function buildMessage( array $input ): array
    {
        return [
            [
                'type' => 'text',
                'text' => sprintf(
                    "Organization: %s\nContact email: %s\nEffective date: %s\nJurisdictions: %s",
                    $input['organization_name'],
                    $input['contact_email'],
                    $input['effective_date'],
                    implode( ', ', $input['jurisdictions'] ),
                ),
            ],
            [
                'type' => 'text',
                'text' => "Processing activities (JSON):\n" . json_encode( $input['processing_activities'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            ],
        ];
    }

    /**
     * Enforce output invariants — coerce `requires_legal_review` to true
     * and guarantee a non-empty review checklist even if the model tries
     * to omit them.
     *
     * @param  array<string, mixed>  $output
     *
     * @return array{
     *     policy_markdown: string,
     *     section_headings: list<string>,
     *     requires_legal_review: true,
     *     review_checklist: list<string>,
     *     jurisdiction_notes: array<string, string>,
     * }
     */
    protected function validateOutput( array $output ): array
    {
        $policy = isset( $output['policy_markdown'] ) ? trim( (string) $output['policy_markdown'] ) : '';

        if ( '' === $policy ) {
            throw FeatureError::forFeature( $this->featureKey, 'model returned an empty policy body.' );
        }

        $headings = [];
        if ( isset( $output['section_headings'] ) && is_array( $output['section_headings'] ) ) {
            foreach ( $output['section_headings'] as $heading ) {
                if ( is_string( $heading ) && '' !== trim( $heading ) ) {
                    $headings[] = trim( $heading );
                }
            }
        }

        $checklist = [];
        if ( isset( $output['review_checklist'] ) && is_array( $output['review_checklist'] ) ) {
            foreach ( $output['review_checklist'] as $item ) {
                if ( is_string( $item ) && '' !== trim( $item ) ) {
                    $checklist[] = trim( $item );
                }
            }
        }

        if ( [] === $checklist ) {
            $checklist = [ 'Have qualified legal counsel review the entire draft before publication.' ];
        }

        $notes = [];
        if ( isset( $output['jurisdiction_notes'] ) && is_array( $output['jurisdiction_notes'] ) ) {
            foreach ( $output['jurisdiction_notes'] as $key => $value ) {
                if ( is_string( $key ) && is_string( $value ) ) {
                    $notes[ $key ] = $value;
                }
            }
        }

        return [
            'policy_markdown'       => $policy,
            'section_headings'      => $headings,
            'requires_legal_review' => true,
            'review_checklist'      => $checklist,
            'jurisdiction_notes'    => $notes,
        ];
    }
}
