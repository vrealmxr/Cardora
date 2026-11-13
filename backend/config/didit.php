<?php

return [
    'enabled' => (bool) env('DIDIT_ENABLED', false),
    'api_key' => env('DIDIT_API_KEY'),
    'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
    'environment' => env('DIDIT_ENVIRONMENT', 'live'),
    'workflow_id' => env('DIDIT_WORKFLOW_ID', 'd057f8df-22ac-4a93-a9fc-aaccfe331f2c'),
    'legacy_workflows' => [
        'd057f8df-22ac-4a93-a9fc-aaccfe331f2c' => [
            'api_key' => env('DIDIT_LEGACY_API_KEY'),
            'webhook_secret' => env('DIDIT_LEGACY_WEBHOOK_SECRET'),
            'environment' => env('DIDIT_LEGACY_ENVIRONMENT', 'live'),
        ],
    ],
    'base_url' => 'https://verification.didit.me/v3',
    'webhook_url' => 'https://api.cardora.gr/index.php/api/webhooks/didit',
    'notice_version' => '2026-09-27.2',
];
