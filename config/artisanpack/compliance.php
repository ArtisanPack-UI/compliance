<?php

return [
    'compliance' => [
        'enabled' => env('COMPLIANCE_ENABLED', true),

        // Default regulation to enforce
        'default_regulation' => env('COMPLIANCE_REGULATION', 'gdpr'),

        // Supported regulations
        'regulations' => ['gdpr', 'ccpa', 'lgpd', 'pipeda', 'popia', 'pdpa'],

        // Data Protection Impact Assessments
        'dpia' => [
            'enabled' => true,
            'auto_require_high_risk' => true,
            'review_reminder_days' => 365,
            'require_dpo_review' => true,
            'export_formats' => ['pdf', 'docx', 'html'],
            // Risk calculation method for overall risk score
            // Options:
            //   'highest' - Use the highest individual risk score (default)
            //   'average' - Use the average of all risk scores
            //   'weighted' - Use weighted average based on severity (critical: 4x, high: 3x, medium: 2x, low: 1x)
            'risk_calculation_method' => 'highest',
        ],

        // Privacy by Design
        'privacy_by_design' => [
            'enabled' => true,
            'auto_encrypt_sensitive' => true,
            'log_data_access' => true,
            'default_retention_days' => null, // null = indefinite
            'audit_trail_enabled' => true,
        ],

        // Data Minimization
        'minimization' => [
            'enabled' => true,
            'enforce_collection_policies' => true,
            'auto_purge_expired' => env('AUTO_PURGE_EXPIRED_DATA', false),
            'purge_batch_size' => 1000,
            'anonymization_algorithm' => 'sha256', // sha256, bcrypt
        ],

        // Right to Erasure
        'erasure' => [
            'enabled' => true,
            'require_identity_verification' => true,
            'verification_methods' => ['email', 'sms', 'document'],
            'grace_period_days' => 7, // Days before permanent deletion
            'deadline_days' => 30, // Legal deadline to respond
            'notify_third_parties' => true,
            'generate_certificate' => true,
            'handlers' => [
                // List of erasure handler classes
            ],
        ],

        // Data Portability
        'portability' => [
            'enabled' => true,
            'deadline_days' => 30,
            'default_format' => 'json',
            'supported_formats' => ['json', 'xml', 'csv'],
            'download_expiry_hours' => 72,
            'max_download_attempts' => 5,
            'allow_direct_transfer' => true,
            'max_export_size_mb' => 500,
            'chunk_size' => 1000,
        ],

        // Consent Management
        'consent' => [
            'enabled' => true,
            'require_explicit' => true,
            'default_expiry_days' => null, // null = no expiry
            'reconsent_on_policy_change' => true,
            'minimum_age' => 16, // GDPR default
            'cookie_consent' => [
                'enabled' => true,
                'banner_position' => 'bottom',
                'categories' => ['essential', 'functional', 'analytics', 'marketing'],
            ],
            'purposes' => [
                'essential' => [
                    'name' => 'Essential',
                    'required' => true,
                    'description' => 'Required for basic site functionality',
                ],
                'functional' => [
                    'name' => 'Functional',
                    'required' => false,
                    'description' => 'Enhanced functionality and personalization',
                ],
                'analytics' => [
                    'name' => 'Analytics',
                    'required' => false,
                    'description' => 'Usage analytics and improvement',
                ],
                'marketing' => [
                    'name' => 'Marketing',
                    'required' => false,
                    'description' => 'Marketing and advertising',
                ],
            ],
        ],

        // Compliance Dashboard
        'dashboard' => [
            'enabled' => true,
            'refresh_interval' => 300, // seconds
            'require_permission' => 'compliance.dashboard.view',
            'score_calculation_cron' => '0 0 * * *', // Daily at midnight
        ],

        // Automated Compliance Checking
        'monitoring' => [
            'enabled' => true,
            'check_schedule' => '0 */6 * * *', // Every 6 hours
            'checks' => [
                'consent_validity' => ['enabled' => true, 'severity' => 'high'],
                'consent_expiration' => ['enabled' => true, 'severity' => 'medium'],
                'retention_expiration' => ['enabled' => true, 'severity' => 'high'],
                'retention_policy' => ['enabled' => true, 'severity' => 'medium'],
                'dsr_timeliness' => ['enabled' => true, 'severity' => 'critical'],
                'dpia_completion' => ['enabled' => true, 'severity' => 'medium'],
                'encryption' => ['enabled' => true, 'severity' => 'critical'],
                'access_control' => ['enabled' => true, 'severity' => 'high'],
            ],
            'alert_on_violation' => true,
            'alert_channels' => ['email', 'slack'],
            'alert_recipients' => [
                'critical' => ['dpo@example.com'],
                'high' => ['compliance@example.com'],
            ],
        ],

        // Reporting
        'reporting' => [
            'enabled' => true,
            'storage_disk' => 'local',
            'storage_path' => 'compliance-reports',
            'default_format' => 'pdf',
            'include_pii' => false, // Include PII in reports
            'retention_days' => 730, // 2 years
        ],

        // Data Categories (GDPR special categories)
        'special_categories' => [
            'racial_ethnic_origin',
            'political_opinions',
            'religious_beliefs',
            'trade_union_membership',
            'genetic_data',
            'biometric_data',
            'health_data',
            'sex_life_orientation',
        ],

        // Legal Bases (GDPR Article 6)
        'legal_bases' => [
            'consent' => 'Consent (Art. 6(1)(a))',
            'contract' => 'Contract (Art. 6(1)(b))',
            'legal_obligation' => 'Legal Obligation (Art. 6(1)(c))',
            'vital_interests' => 'Vital Interests (Art. 6(1)(d))',
            'public_interest' => 'Public Interest (Art. 6(1)(e))',
            'legitimate_interests' => 'Legitimate Interests (Art. 6(1)(f))',
        ],
    ],
];
