<?php

// ─────────────────────────────────────────────────────────────
// FILE: config/questionbank.php
// ─────────────────────────────────────────────────────────────

return [
    'import' => [
        'max_file_size'  => env('QUESTION_IMPORT_MAX_FILE_SIZE', 20480),  // KB
        'batch_size'     => env('QUESTION_IMPORT_BATCH_SIZE', 50),
        'max_rows'       => env('QUESTION_IMPORT_MAX_ROWS', 10000),
        'allowed_types'  => ['xlsx', 'xls'],
        'queue'          => 'imports',
    ],

    'pagination' => [
        'default' => env('QUESTION_PER_PAGE_DEFAULT', 25),
        'max'     => env('QUESTION_PER_PAGE_MAX', 100),
    ],

    'search' => [
        'min_length' => env('QUESTION_SEARCH_MIN_LENGTH', 3),
        'driver'     => env('QUESTION_SEARCH_DRIVER', 'mysql'), // mysql | meilisearch
    ],

    'cache' => [
        'enabled' => env('QUESTION_CACHE_ENABLED', true),
        'prefix'  => 'qbank',
        'ttl'     => [
            'subjects'     => 3600,   // 1 hour
            'topics'       => 3600,   // 1 hour
            'tags'         => 1800,   // 30 minutes
            'stats'        => 300,    // 5 minutes
            'question'     => 600,    // 10 minutes
            'aggregations' => 300,    // 5 minutes
        ],
    ],

    'types' => [
        'mcq', 'multi_select', 'true_false',
        'short_answer', 'long_answer',
        'fill_blank', 'match_column',
    ],

    'difficulties' => ['easy', 'medium', 'hard', 'expert'],

    'statuses' => ['draft', 'review', 'approved', 'archived', 'rejected'],
];
