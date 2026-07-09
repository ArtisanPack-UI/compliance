<?php

/**
 * DPIA (Data Protection Impact Assessment) assistance agent.
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
 * Assists with DPIA drafting: enumerates risks, suggests mitigations,
 * and calls out stakeholder impacts.
 *
 * **High-stakes agent**: every output carries a `requires_legal_review`
 * marker and a mandatory review checklist. The host UI must gate the
 * draft behind a legal-review acknowledgement.
 *
 * ## Input
 *
 * ```
 * [
 *   'activity_name'      => string,          // required
 *   'activity_purpose'   => string,          // required
 *   'data_categories'    => list<string>,    // required, non-empty
 *   'data_subjects'      => list<string>,    // required, non-empty (e.g. "customers", "employees")
 *   'processing_scale'   => string,          // required (e.g. "small", "medium", "large")
 *   'systems_involved'   => list<string>,    // optional
 *   'transfers_outside_eea' => bool,         // optional (defaults false)
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   risks: list<{
 *     title: string,
 *     description: string,
 *     likelihood: "low"|"medium"|"high",
 *     severity: "low"|"medium"|"high",
 *     affected_subjects: list<string>,
 *   }>,
 *   mitigations: list<{
 *     risk_title: string,
 *     mitigation: string,
 *     residual_risk: "low"|"medium"|"high",
 *   }>,
 *   stakeholder_impacts: list<{
 *     stakeholder: string,
 *     impact: string,
 *   }>,
 *   requires_legal_review: true,
 *   review_checklist: list<string>,
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage Compliance
 *
 * @since      1.1.0
 */
class DpiaAssistanceAgent extends ArtisanPackAgent
{

    private const RISK_LEVELS = [ 'low', 'medium', 'high' ];

    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'compliance.dpia_assistance';

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
You assist with drafting a Data Protection Impact Assessment (DPIA). You are not a lawyer or a DPO and the output is not legal advice.

Non-negotiables:
- Draft is a STARTING POINT that a DPO or qualified legal counsel must review and validate.
- Base every risk, mitigation, and stakeholder impact on the declared processing activity. Do NOT invent facts.
- Rate likelihood and severity honestly. A "low" severity for the loss of medical data is wrong.
- Prefer specific mitigations ("encrypt payload column with libsodium XChaCha20-Poly1305") over vague ones ("use encryption").
- Every risk you enumerate MUST have a matching mitigation entry (linked by `risk_title`).
- Call out cross-border transfers, automated decision-making, and processing of special-category data as distinct risks when applicable.

Return a JSON object with:
- `risks`: list of risk objects (title, description, likelihood in ["low","medium","high"], severity in ["low","medium","high"], affected_subjects list).
- `mitigations`: list of mitigation objects (risk_title matching a risk, mitigation description, residual_risk level).
- `stakeholder_impacts`: list of impact objects (stakeholder identifier, impact description).
- `requires_legal_review`: MUST be `true`.
- `review_checklist`: list of items the reviewing DPO/counsel must verify (e.g. "Confirm Article 35(3) mandatory-DPIA criteria assessed correctly"). Include at minimum: risk ratings, mitigation effectiveness, any special-category data handling, and cross-border-transfer safeguards.
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
                'risks',
                'mitigations',
                'stakeholder_impacts',
                'requires_legal_review',
                'review_checklist',
            ],
            'properties' => [
                'risks' => [
                    'type'     => 'array',
                    'minItems' => 1,
                    'items'    => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => [ 'title', 'description', 'likelihood', 'severity', 'affected_subjects' ],
                        'properties'           => [
                            'title'             => [ 'type' => 'string' ],
                            'description'       => [ 'type' => 'string' ],
                            'likelihood'        => [ 'type' => 'string', 'enum' => self::RISK_LEVELS ],
                            'severity'          => [ 'type' => 'string', 'enum' => self::RISK_LEVELS ],
                            'affected_subjects' => [
                                'type'  => 'array',
                                'items' => [ 'type' => 'string' ],
                            ],
                        ],
                    ],
                ],
                'mitigations' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => [ 'risk_title', 'mitigation', 'residual_risk' ],
                        'properties'           => [
                            'risk_title'    => [ 'type' => 'string' ],
                            'mitigation'    => [ 'type' => 'string' ],
                            'residual_risk' => [ 'type' => 'string', 'enum' => self::RISK_LEVELS ],
                        ],
                    ],
                ],
                'stakeholder_impacts' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => [ 'stakeholder', 'impact' ],
                        'properties'           => [
                            'stakeholder' => [ 'type' => 'string' ],
                            'impact'      => [ 'type' => 'string' ],
                        ],
                    ],
                ],
                'requires_legal_review' => [ 'type' => 'boolean' ],
                'review_checklist'      => [
                    'type'     => 'array',
                    'minItems' => 1,
                    'items'    => [ 'type' => 'string' ],
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
     * @return array<string, mixed>
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature( $this->featureKey, 'input must be an array.' );
        }

        $required = [ 'activity_name', 'activity_purpose', 'processing_scale' ];
        foreach ( $required as $key ) {
            if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) || '' === trim( $input[ $key ] ) ) {
                throw FeatureError::forFeature( $this->featureKey, sprintf( '`%s` must be a non-empty string.', $key ) );
            }
        }

        foreach ( [ 'data_categories', 'data_subjects' ] as $listKey ) {
            if ( ! isset( $input[ $listKey ] ) || ! is_array( $input[ $listKey ] ) || [] === $input[ $listKey ] ) {
                throw FeatureError::forFeature( $this->featureKey, sprintf( '`%s` must be a non-empty array.', $listKey ) );
            }
        }

        $systems = isset( $input['systems_involved'] ) && is_array( $input['systems_involved'] )
            ? array_values( array_map( 'strval', $input['systems_involved'] ) )
            : [];

        return [
            'activity_name'         => trim( $input['activity_name'] ),
            'activity_purpose'      => trim( $input['activity_purpose'] ),
            'data_categories'       => array_values( array_map( 'strval', $input['data_categories'] ) ),
            'data_subjects'         => array_values( array_map( 'strval', $input['data_subjects'] ) ),
            'processing_scale'      => trim( $input['processing_scale'] ),
            'systems_involved'      => $systems,
            'transfers_outside_eea' => (bool) ( $input['transfers_outside_eea'] ?? false ),
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
                    "Activity: %s\nPurpose: %s\nProcessing scale: %s\nTransfers outside EEA: %s",
                    $input['activity_name'],
                    $input['activity_purpose'],
                    $input['processing_scale'],
                    $input['transfers_outside_eea'] ? 'yes' : 'no',
                ),
            ],
            [
                'type' => 'text',
                'text' => "Context (JSON):\n" . json_encode( [
                    'data_categories'  => $input['data_categories'],
                    'data_subjects'    => $input['data_subjects'],
                    'systems_involved' => $input['systems_involved'],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
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
        $risks       = $this->normalizeList( $output['risks'] ?? null );
        $mitigations = $this->normalizeList( $output['mitigations'] ?? null );
        $impacts     = $this->normalizeList( $output['stakeholder_impacts'] ?? null );

        if ( [] === $risks ) {
            throw FeatureError::forFeature( $this->featureKey, 'model returned no risks.' );
        }

        $mitigationTitles = [];
        foreach ( $mitigations as $mitigation ) {
            if ( isset( $mitigation['risk_title'] ) && is_string( $mitigation['risk_title'] ) ) {
                $mitigationTitles[] = trim( $mitigation['risk_title'] );
            }
        }

        foreach ( $risks as $risk ) {
            $title = isset( $risk['title'] ) && is_string( $risk['title'] ) ? trim( $risk['title'] ) : '';

            if ( '' === $title || ! in_array( $title, $mitigationTitles, true ) ) {
                throw FeatureError::forFeature(
                    $this->featureKey,
                    sprintf( 'Every risk must have a matching mitigation. Risk "%s" has none.', $title ),
                );
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
            $checklist = [ 'Have a DPO or qualified legal counsel review the DPIA before it is filed.' ];
        }

        return [
            'risks'                 => $risks,
            'mitigations'           => $mitigations,
            'stakeholder_impacts'   => $impacts,
            'requires_legal_review' => true,
            'review_checklist'      => $checklist,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeList( mixed $value ): array
    {
        if ( ! is_array( $value ) ) {
            return [];
        }

        $out = [];
        foreach ( $value as $item ) {
            if ( is_array( $item ) ) {
                $out[] = $item;
            }
        }
        return $out;
    }
}
