<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Host models (soft references only — never FK-constrained)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'user' => env('CRM_USER_MODEL', 'App\\Models\\User'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Key type strategy
    |--------------------------------------------------------------------------
    |
    | user_key_type: 'int' | 'uuid' | 'ulid'
    | branch_key_type: same set, or null to inherit user_key_type.
    | assigned_to always follows user_key_type (it points at a host User).
    |
    */
    'keys' => [
        'user_key_type' => env('CRM_USER_KEY_TYPE', 'int'),
        'branch_key_type' => env('CRM_BRANCH_KEY_TYPE', 'int'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table prefix
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'prefix' => env('CRM_TABLE_PREFIX', 'crm_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring policy (locked formula — see docs/concepts/scoring.md)
    |--------------------------------------------------------------------------
    |
    | effective_weight = interaction.weight × scoring.weights[type]
    | Interest.score   = Σ (effective_weight × decay(age_in_days))
    |
    | Unknown interaction types use default_weight (not an exception).
    |
    */
    'scoring' => [
        'weights' => [
            'viewed' => 1,
            'clicked' => 2,
            'requested' => 3,
            'purchased' => 5,
            'enrolled' => 5,
            'refunded' => 0,
        ],
        'default_weight' => 0,
        'decay_half_life_days' => 30,
        'thresholds' => [
            'affinity_alert' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sales attribution (Owner ≠ Attribution)
    |--------------------------------------------------------------------------
    |
    | window_days is configurable — never treat a fixed 7/30 as domain truth.
    | default_policy: first_touch | last_touch | active_opportunity | manual
    |
    */
    'attribution' => [
        'window_days' => (int) env('CRM_ATTRIBUTION_WINDOW_DAYS', 30),
        'default_policy' => env('CRM_ATTRIBUTION_DEFAULT_POLICY', 'active_opportunity'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection (overdue invoices → CRM Tasks)
    |--------------------------------------------------------------------------
    */
    'collection' => [
        'due_days_after_invoice' => (int) env('CRM_COLLECTION_DUE_DAYS', 7),
        'task_type' => 'collection',
    ],
];
