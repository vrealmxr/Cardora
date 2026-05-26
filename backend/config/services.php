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
