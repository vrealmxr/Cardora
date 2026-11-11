<?php

return [
    'enabled' => (bool) env('DIDIT_ENABLED', false),
    'api_key' => env('DIDIT_API_KEY'),
    'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
    'environment' => env('DIDIT_ENVIRONMENT', 'live'),
    'workflow_id' => 'ab007d91-e9ed-4d63-af3d-88e0a135068d',
    'base_url' => 'https://verification.didit.me/v3',
    'webhook_url' => 'https://api.cardora.gr/index.php/api/webhooks/didit',
    'notice_version' => '2026-09-27.2',
];
