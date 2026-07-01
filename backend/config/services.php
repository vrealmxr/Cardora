<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'connect_country' => env('STRIPE_CONNECT_COUNTRY', 'GR'),
        'marketplace_commission_rate' => (float) env('STRIPE_MARKETPLACE_COMMISSION_RATE', 0.00),
        'marketplace_buyer_fee_rate' => (float) env('STRIPE_MARKETPLACE_BUYER_FEE_RATE', 0.00),
        'confirmation_window_days' => (int) env('STRIPE_CONFIRMATION_WINDOW_DAYS', 7),
        'checkout_success_url' => env('STRIPE_CHECKOUT_SUCCESS_URL', env('FRONTEND_URL', 'http://localhost:5173').'/checkout/success'),
        'checkout_cancel_url' => env('STRIPE_CHECKOUT_CANCEL_URL', env('FRONTEND_URL', 'http://localhost:5173').'/checkout'),
        'featured_success_url' => env('STRIPE_FEATURED_SUCCESS_URL', env('FRONTEND_URL', 'http://localhost:5173').'/oi-aggelies-mou'),
        'featured_cancel_url' => env('STRIPE_FEATURED_CANCEL_URL', env('FRONTEND_URL', 'http://localhost:5173').'/oi-aggelies-mou'),
        'featured_listing_price' => (float) env('STRIPE_FEATURED_LISTING_PRICE', 2),
        'featured_listing_currency' => env('STRIPE_FEATURED_LISTING_CURRENCY', 'EUR'),
        'featured_listing_duration_days' => (int) env('STRIPE_FEATURED_LISTING_DURATION_DAYS', 5),
        'connect_return_frontend_url' => env('STRIPE_CONNECT_RETURN_FRONTEND_URL', env('FRONTEND_URL', 'http://localhost:5173').'/dashboard-politi'),
        'connect_refresh_frontend_url' => env('STRIPE_CONNECT_REFRESH_FRONTEND_URL', env('FRONTEND_URL', 'http://localhost:5173').'/dashboard-politi?stripe_connect=refresh'),
        'trade_fee_rate' => (float) env('STRIPE_TRADE_FEE_RATE', 0.05),
        'trade_success_url' => env('STRIPE_TRADE_SUCCESS_URL', env('FRONTEND_URL', 'http://localhost:5173').'/dashboard-politi/kliroseis'),
        'trade_cancel_url' => env('STRIPE_TRADE_CANCEL_URL', env('FRONTEND_URL', 'http://localhost:5173').'/dashboard-politi/kliroseis'),
    ],

    'dhl' => [
        'enabled' => (bool) env('DHL_API_ENABLED', false),
        'use_test_environment' => (bool) env('DHL_API_USE_TEST_ENVIRONMENT', true),
        'base_url' => env('DHL_API_BASE_URL', 'https://express.api.dhl.com/mydhlapi'),
        'test_base_url' => env('DHL_API_TEST_BASE_URL', 'https://express.api.dhl.com/mydhlapi/test'),
        'username' => env('DHL_API_USERNAME'),
        'password' => env('DHL_API_PASSWORD'),
        'account_number' => env('DHL_API_ACCOUNT_NUMBER'),
        'default_product_code' => env('DHL_API_DEFAULT_PRODUCT_CODE', 'N'),
        'default_package_weight_kg' => (float) env('DHL_API_DEFAULT_PACKAGE_WEIGHT_KG', 0.5),
        'auto_release_days_after_delivery' => (int) env('DHL_AUTO_RELEASE_DAYS_AFTER_DELIVERY', 2),
    ],

    'boxnow' => [
        // BoxNow shipping is on hold until the BoxNow API credentials are provisioned.
        // Keep this disabled so buyers are not able to check out orders we cannot yet fulfil or track.
        'enabled' => (bool) env('BOXNOW_API_ENABLED', false),
        'use_test_environment' => (bool) env('BOXNOW_API_USE_TEST_ENVIRONMENT', true),
        'base_url' => env('BOXNOW_API_BASE_URL', 'https://api-production.boxnow.gr'),
        'test_base_url' => env('BOXNOW_API_TEST_BASE_URL', 'https://api-stage.boxnow.gr'),
        'client_id' => env('BOXNOW_API_CLIENT_ID'),
        'client_secret' => env('BOXNOW_API_CLIENT_SECRET'),
        'warehouse_id' => env('BOXNOW_API_WAREHOUSE_ID'),
        // Endpoint paths are overridable so they can be aligned with the official API docs.
        'auth_path' => env('BOXNOW_API_AUTH_PATH', '/api/v1/auth-sessions'),
        'shipments_path' => env('BOXNOW_API_SHIPMENTS_PATH', '/api/v1/delivery-requests'),
        'lockers_path' => env('BOXNOW_API_LOCKERS_PATH', '/api/v1/destinations/points'),
        'default_package_weight_kg' => (float) env('BOXNOW_API_DEFAULT_PACKAGE_WEIGHT_KG', 0.5),
        'auto_release_days_after_delivery' => (int) env('BOXNOW_AUTO_RELEASE_DAYS_AFTER_DELIVERY', 2),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:5173'),
        'email_verification_url' => env('AUTH_EMAIL_VERIFICATION_URL', env('FRONTEND_URL', 'http://localhost:5173').'/epivevaiosi-email'),
        'password_reset_url' => env('AUTH_PASSWORD_RESET_URL', env('FRONTEND_URL', 'http://localhost:5173').'/epanafora-kodikou'),
        'email_verification_expire_minutes' => (int) env('AUTH_EMAIL_VERIFICATION_EXPIRE_MINUTES', 1440),
        'google_auth_callback_url' => env('AUTH_GOOGLE_CALLBACK_URL', env('FRONTEND_URL', 'http://localhost:5173').'/auth/google/callback'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/index.php/api/auth/google/callback'),
    ],

];
