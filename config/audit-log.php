<?php

return [
    'enabled' => env('AUDIT_LOG_ENABLED', true),

    'table_name' => env('AUDIT_LOG_TABLE', 'audit_logs'),

    'connection' => env('AUDIT_LOG_CONNECTION'),

    'default_log_name' => 'default',

    'auto_discover_actor' => true,

    'exclude' => [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'client_secret',
    ],

    'model' => [
        'events' => ['created', 'updated', 'deleted'],
    ],

    'queue' => [
        'enabled' => false,
        'include_exception_message' => false,
        'events' => [
            'queued',
            'processing',
            'processed',
            'failed',
        ],
    ],

    'retention_days' => 365,
];
