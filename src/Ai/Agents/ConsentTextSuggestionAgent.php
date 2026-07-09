<?php

/**
 * Consent-text suggestion agent.
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
 * Suggest clear, plain-language consent text for a given processing
 * purpose. Optimizes for readability + regulatory clarity.
 *
 * Lower-stakes than the policy/DPIA agents but still gated behind a
 * `requires_legal_review` marker — consent language is enforceable.
 *
 * ## Input
 *
 * ```
 * [
 *   'purpose'          => string,       // required, what the consent covers
 *   'data_categories'  => list<string>, // required, non-empty
 *   'audience'         => string,       // required (e.g. "general public", "healthcare patients")
 *   'jurisdiction'     => string,       // required (e.g. "EU", "US-CA", "BR")
 *   'target_reading_level' => int|null, // optional grade level (6-14), default 8
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   consent_text: string,             // <= 800 chars, plain language
 *   short_label: string,              // <= 80 chars, checkbox label
 *   reading_level: {
 *     grade: number,                  // Flesch-Kincaid grade
 *     score: number,                  // Flesch reading ease
 *   },
 *   jurisdiction_notes: list<string>, // things a reviewer must confirm
 *   requires_legal_review: true,
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */
class ConsentTextSuggestionAgent extends ArtisanPackAgent
{
    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'compliance.consent_text';

    /**
     * {@inheritDoc}
     */
    public string $package = 'artisanpack-ui/compliance';

    /**
     * {@inheritDoc}
     */
    public string $defaultModel = 'claude-sonnet-4-6';

    /**
     * {@inheritDoc}
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You write consent language that a real person can understand on the first read.

Non-negotiables:
- Plain language. Target the reader's grade level (default 8). Prefer short sentences and everyday words.
- No pre-checked-consent framing. Consent must read as an active choice.
- Name the specific data categories and the specific purpose. No vague "your information" or "to improve our services".
- Do not imply consent covers anything the caller did not list.
- Jurisdiction-specific requirements matter: EU/UK (GDPR — freely given, specific, informed), US-CA (CCPA — right to opt out of sale/share), BR (LGPD — free, informed, unambiguous, specific), etc. Note relevant caveats in `jurisdiction_notes`.

Return a JSON object with:
- `consent_text` (string, <= 800 chars): the full consent paragraph.
- `short_label` (string, <= 80 chars): a checkbox-label version, e.g. "I agree to receive product update emails.".
- `reading_level` (object): { grade: number (Flesch-Kincaid grade), score: number (Flesch reading-ease score 0-100) }. Estimate honestly; do not fabricate.
- `jurisdiction_notes` (list of strings): items a reviewer must confirm for the target jurisdiction.
- `requires_legal_review` (boolean): MUST be `true`.
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
                'consent_text',
                'short_label',
                'reading_level',
                'jurisdiction_notes',
                'requires_legal_review',
            ],
            'properties' => [
                'consent_text'  => [ 'type' => 'string' ],
                'short_label'   => [ 'type' => 'string' ],
                'reading_level' => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => [ 'grade', 'score' ],
                    'properties'           => [
                        'grade' => [ 'type' => 'number' ],
                        'score' => [ 'type' => 'number' ],
                    ],
                ],
                'jurisdiction_notes' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
                'requires_legal_review' => [ 'type' => 'boolean' ],
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
     * @return array{
     *     purpose: string,
     *     data_categories: list<string>,
     *     audience: string,
     *     jurisdiction: string,
     *     target_reading_level: int,
     * }
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature( $this->featureKey, 'input must be an array.' );
        }

        foreach ( [ 'purpose', 'audience', 'jurisdiction' ] as $key ) {
            if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) || '' === trim( $input[ $key ] ) ) {
                throw FeatureError::forFeature( $this->featureKey, sprintf( '`%s` must be a non-empty string.', $key ) );
            }
        }

        if ( ! isset( $input['data_categories'] ) || ! is_array( $input['data_categories'] ) || [] === $input['data_categories'] ) {
            throw FeatureError::forFeature( $this->featureKey, '`data_categories` must be a non-empty array.' );
        }

        $grade = 8;
        if ( isset( $input['target_reading_level'] ) ) {
            $parsed = filter_var(
                $input['target_reading_level'],
                FILTER_VALIDATE_INT,
                [ 'options' => [ 'min_range' => 6, 'max_range' => 14 ] ],
            );
            if ( false !== $parsed ) {
                $grade = $parsed;
            }
        }

        return [
            'purpose'              => trim( $input['purpose'] ),
            'data_categories'      => array_values( array_map( 'strval', $input['data_categories'] ) ),
            'audience'             => trim( $input['audience'] ),
            'jurisdiction'         => trim( $input['jurisdiction'] ),
            'target_reading_level' => $grade,
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
                    "Purpose: %s\nAudience: %s\nJurisdiction: %s\nTarget grade level: %d",
                    $input['purpose'],
                    $input['audience'],
                    $input['jurisdiction'],
                    $input['target_reading_level'],
                ),
            ],
            [
                'type' => 'text',
                'text' => 'Data categories: ' . implode( ', ', $input['data_categories'] ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     *
     * @return array<string, mixed>
     */
    protected function validateOutput( array $output ): array
    {
        $consent = isset( $output['consent_text'] ) ? trim( (string) $output['consent_text'] ) : '';
        $label   = isset( $output['short_label'] ) ? trim( (string) $output['short_label'] ) : '';

        if ( '' === $consent ) {
            throw FeatureError::forFeature( $this->featureKey, 'model returned empty consent text.' );
        }

        if ( '' === $label ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'model returned an empty short_label — an unlabelled consent checkbox does not meet the "clear affirmative act" standard.',
            );
        }

        if ( mb_strlen( $consent ) > 800 ) {
            $consent = mb_substr( $consent, 0, 800 );
        }

        if ( mb_strlen( $label ) > 80 ) {
            $label = mb_substr( $label, 0, 80 );
        }

        $reading = $output['reading_level'] ?? null;
        $grade   = is_array( $reading ) && isset( $reading['grade'] ) && is_numeric( $reading['grade'] ) ? (float) $reading['grade'] : 0.0;
        $score   = is_array( $reading ) && isset( $reading['score'] ) && is_numeric( $reading['score'] ) ? (float) $reading['score'] : 0.0;

        $notes = [];
        if ( isset( $output['jurisdiction_notes'] ) && is_array( $output['jurisdiction_notes'] ) ) {
            foreach ( $output['jurisdiction_notes'] as $note ) {
                if ( is_string( $note ) && '' !== trim( $note ) ) {
                    $notes[] = trim( $note );
                }
            }
        }

        if ( [] === $notes ) {
            $notes = [ 'Have qualified legal counsel confirm the language meets the target jurisdiction\'s consent standard.' ];
        }

        return [
            'consent_text'          => $consent,
            'short_label'           => $label,
            'reading_level'         => [ 'grade' => $grade, 'score' => $score ],
            'jurisdiction_notes'    => $notes,
            'requires_legal_review' => true,
        ];
    }
}
