<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Compliance\Compliance\Assessment;

use ArtisanPackUI\Compliance\Models\ProcessingActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProcessingActivityService
{
    /**
     * Array fields that should be validated/normalized.
     *
     * @var array<string>
     */
    protected array $arrayFields = [
        'purposes',
        'legal_bases',
        'data_categories',
        'data_subjects',
        'recipients',
        'third_countries',
        'safeguards',
        'retention_policy',
        'security_measures',
        'automated_decisions',
    ];

    /**
     * Valid status values.
     *
     * @var array<string>
     */
    protected array $validStatuses = ['active', 'suspended', 'terminated', 'draft'];

    /**
     * Create a new processing activity record.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create( array $data ): ProcessingActivity
    {
        $this->validateInput( $data );

        $sanitized = $this->sanitizeData( $data );

        return ProcessingActivity::create( [
            'name'                => $sanitized['name'],
            'description'         => $sanitized['description'] ?? null,
            'controller_name'     => $sanitized['controller_name'] ?? config( 'app.name' ),
            'controller_contact'  => $sanitized['controller_contact'] ?? null,
            'processor_name'      => $sanitized['processor_name'] ?? null,
            'processor_contact'   => $sanitized['processor_contact'] ?? null,
            'dpo_contact'         => $sanitized['dpo_contact'] ?? null,
            'purposes'            => $sanitized['purposes'],
            'legal_bases'         => $sanitized['legal_bases'],
            'data_categories'     => $sanitized['data_categories'],
            'data_subjects'       => $sanitized['data_subjects'],
            'recipients'          => $sanitized['recipients'],
            'third_countries'     => $sanitized['third_countries'],
            'safeguards'          => $sanitized['safeguards'],
            'retention_policy'    => $sanitized['retention_policy'],
            'security_measures'   => $sanitized['security_measures'],
            'automated_decisions' => $sanitized['automated_decisions'],
            'dpia_required'       => $this->determineDpiaRequired( $sanitized ),
            'status'              => 'active',
            'next_review_at'      => $sanitized['next_review_at'] ?? now()->addYear(),
        ] );
    }

    /**
     * Update a processing activity.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update( ProcessingActivity $activity, array $data ): ProcessingActivity
    {
        $this->validateInput( $data, true );

        // Recalculate DPIA requirement if data categories, automated_decisions, or purposes changed
        if ( isset( $data['data_categories'] ) || isset( $data['automated_decisions'] ) || isset( $data['purposes'] ) ) {
            $data['dpia_required'] = $this->determineDpiaRequired( array_merge(
                $activity->toArray(),
                $data,
            ) );
        }

        // Merge last_review_at into the data array for a single update
        $data['last_review_at'] = now();

        $activity->update( $data );

        return $activity;
    }

    /**
     * Get all active processing activities.
     */
    public function getActive(): Collection
    {
        return ProcessingActivity::active()->get();
    }

    /**
     * Get activities requiring DPIA.
     */
    public function getRequiringDpia(): Collection
    {
        return ProcessingActivity::active()
            ->requiresDpia()
            ->get();
    }

    /**
     * Get activities due for review.
     */
    public function getDueForReview(): Collection
    {
        return ProcessingActivity::active()
            ->whereNotNull( 'next_review_at' )
            ->where( 'next_review_at', '<=', now() )
            ->get();
    }

    /**
     * Search activities.
     *
     * @param  array<string, mixed>  $filters
     */
    public function search( array $filters = [] ): Collection
    {
        $query = ProcessingActivity::query();

        if ( isset( $filters['status'] ) ) {
            $query->where( 'status', $filters['status'] );
        }

        if ( isset( $filters['dpia_required'] ) ) {
            $query->where( 'dpia_required', $filters['dpia_required'] );
        }

        if ( isset( $filters['purpose'] ) ) {
            $query->whereJsonContains( 'purposes', $filters['purpose'] );
        }

        if ( isset( $filters['data_category'] ) ) {
            $query->whereJsonContains( 'data_categories', $filters['data_category'] );
        }

        if ( isset( $filters['search'] ) ) {
            $query->where( function ( $q ) use ( $filters ): void {
                $q->where( 'name', 'like', '%' . $filters['search'] . '%' )
                    ->orWhere( 'description', 'like', '%' . $filters['search'] . '%' );
            } );
        }

        return $query->get();
    }

    /**
     * Generate Records of Processing Activities (ROPA).
     *
     * @return array<string, mixed>
     */
    public function generateRopa(): array
    {
        $activities = ProcessingActivity::active()->get();

        return [
            'generated_at' => now()->toIso8601String(),
            'organization' => [
                'name'        => config( 'app.name' ),
                'dpo_contact' => config( 'artisanpack.compliance.compliance.dpia.dpo_contact' ),
            ],
            'activities' => $activities->map( function ( ProcessingActivity $activity ) {
                return [
                    'name'              => $activity->name,
                    'description'       => $activity->description,
                    'purposes'          => $activity->purposes,
                    'legal_bases'       => $activity->legal_bases,
                    'data_categories'   => $activity->data_categories,
                    'data_subjects'     => $activity->data_subjects,
                    'recipients'        => $activity->recipients,
                    'third_countries'   => $activity->third_countries,
                    'safeguards'        => $activity->safeguards,
                    'retention'         => $activity->retention_policy,
                    'security_measures' => $activity->security_measures,
                    'dpia_required'     => $activity->dpia_required,
                    'dpia_reference'    => $activity->dpia_reference,
                ];
            } )->toArray(),
        ];
    }

    /**
     * Suspend a processing activity.
     */
    public function suspend( ProcessingActivity $activity, string $reason ): ProcessingActivity
    {
        $activity->update( [
            'status'            => 'suspended',
            'suspension_reason' => $reason,
            'suspended_at'      => now(),
        ] );

        return $activity;
    }

    /**
     * Terminate a processing activity.
     */
    public function terminate( ProcessingActivity $activity ): ProcessingActivity
    {
        $activity->update( [
            'status' => 'terminated',
        ] );

        return $activity;
    }

    /**
     * Validate input data for creating/updating a processing activity.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    protected function validateInput( array $data, bool $isUpdate = false ): void
    {
        $rules = [
            'name'                => $isUpdate ? 'sometimes|required|string|min:1|max:255' : 'required|string|min:1|max:255',
            'description'         => 'nullable|string|max:65535',
            'controller_name'     => 'nullable|string|max:255',
            'controller_contact'  => 'nullable|string|max:255',
            'processor_name'      => 'nullable|string|max:255',
            'processor_contact'   => 'nullable|string|max:255',
            'dpo_contact'         => 'nullable|string|max:255',
            'purposes'            => 'nullable|array',
            'legal_bases'         => 'nullable|array',
            'data_categories'     => 'nullable|array',
            'data_subjects'       => 'nullable|array',
            'recipients'          => 'nullable|array',
            'third_countries'     => 'nullable|array',
            'safeguards'          => 'nullable|array',
            'retention_policy'    => 'nullable|array',
            'security_measures'   => 'nullable|array',
            'automated_decisions' => 'nullable|array',
            'next_review_at'      => 'nullable|date',
            'status'              => 'nullable|string|in:' . implode( ',', $this->validStatuses ),
        ];

        $validator = Validator::make( $data, $rules );

        if ( $validator->fails() ) {
            throw new ValidationException( $validator );
        }
    }

    /**
     * Sanitize and normalize input data.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    protected function sanitizeData( array $data ): array
    {
        // Ensure all array fields are arrays
        foreach ( $this->arrayFields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $data[ $field ] = is_array( $data[ $field ] ) ? $data[ $field ] : [];
            } else {
                $data[ $field ] = [];
            }
        }

        // Normalize next_review_at date
        if ( isset( $data['next_review_at'] ) && is_string( $data['next_review_at'] ) ) {
            $data['next_review_at'] = \Carbon\Carbon::parse( $data['next_review_at'] );
        }

        return $data;
    }

    /**
     * Determine if DPIA is required.
     *
     * @param  array<string, mixed>  $data
     */
    protected function determineDpiaRequired( array $data ): bool
    {
        // Check for special categories - ensure config value is an array
        $specialCategories = config( 'artisanpack.compliance.compliance.special_categories' );
        $specialCategories = is_array( $specialCategories ) ? $specialCategories : [];
        $dataCategories    = $data['data_categories'] ?? [];
        $dataCategories    = is_array( $dataCategories ) ? $dataCategories : [];

        if ( ! empty( array_intersect( $dataCategories, $specialCategories ) ) ) {
            return true;
        }

        // Check for automated decision making with legal effects
        if ( ! empty( $data['automated_decisions'] ) ) {
            return true;
        }

        // Check for large scale monitoring
        $purposes = $data['purposes'] ?? [];
        $purposes = is_array( $purposes ) ? $purposes : [];
        if ( in_array( 'systematic_monitoring', $purposes ) ) {
            return true;
        }

        return false;
    }
}
