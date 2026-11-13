<?php

return [
    'enabled' => (bool) env('CARD_SCANS_ENABLED', false),
    'root' => env('CARD_SCANS_ROOT') ?: storage_path('app/private-card-scans'),
    'php_binary' => env('CARD_SCANS_PHP') ?: PHP_BINARY,
    'spawn_worker' => (bool) env('CARD_SCANS_SPAWN_WORKER', false),
    'endpoint' => 'https://s7iolauwqomxj4.api.runpod.ai',
    'runpod_key' => env('CARD_SCANS_RUNPOD_KEY', ''),
    'app_key' => env('CARD_SCANS_APP_KEY', ''),
    'model_revision' => 'fc6779f3e8dc324d118511f51b57bc1a50d8c5d8',
    'daily_limit' => (int) env('CARD_SCANS_DAILY_LIMIT', 20),
    'user_daily_limit' => (int) env('CARD_SCANS_USER_DAILY_LIMIT', 5),
    'max_bytes' => (int) env('CARD_SCANS_MAX_BYTES', 2147483648),
    'policy_version' => 'scan-auto-crop-terms-2026-10-21',
    'categories' => [
        'pokemon'=>'Pokémon', 'yugioh'=>'Yu-Gi-Oh!', 'lorcana'=>'Disney Lorcana',
        'dragonball'=>'Dragon Ball', 'magic'=>'Magic: The Gathering', 'riftbound'=>'Riftbound',
        'onepiece'=>'One Piece', 'starwars'=>'Star Wars', 'nba'=>'NBA', 'soccer'=>'Soccer', 'euroleague'=>'EuroLeague',
    ],
];
