<?php

/**
 * Masking Configuration
 *
 * Define custom column patterns for sensitive data masking.
 * Columns matching any of these patterns will have their values
 * replaced with masked equivalents during database cloning.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Sensitive Column Patterns
    |--------------------------------------------------------------------------
    |
    | List of column name patterns (case-insensitive substring match) that
    | should be masked. Add your custom patterns here.
    |
    */
    'sensitive_patterns' => [
        // Identity & credentials
        'password',
        'passwd',
        'secret',
        'token',
        'api_key',
        'auth_key',

        // Personal identifiers
        'ssn',
        'national_id',
        'passport',
        'tax_id',
        'tax_reference',
        'fiscal_id',

        // Financial
        'credit_card',
        'card_number',
        'iban',
        'bank_account',
        'routing_number',

        // Contact information
        'email',
        'phone',
        'mobile',
        'address',

        // Health & sensitive data
        'medical',
        'health',
        'diagnosis',
        'dob',
        'date_of_birth',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mask Replacement Values
    |--------------------------------------------------------------------------
    |
    | Define how masked values should look for different column types.
    |
    */
    'mask_values' => [
        'email'    => 'masked@example.com',
        'phone'    => '+49-000-000-0000',
        'password' => '$2y$10$MASKED_HASH_PLACEHOLDER_ONLY',
        'default'  => '*** MASKED ***',
    ],
];
