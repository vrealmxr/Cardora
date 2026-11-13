<?php
// Pure service tests: no Laravel boot, database, queue, network or .env loading.
$autoload = getenv('CARDORA_TEST_VENDOR_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
require $autoload;
require_once dirname(__DIR__).'/app/Services/MessageModerationService.php';
