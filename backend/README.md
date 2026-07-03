# Cardora Backend

Laravel backend for Cardora marketplace, including API endpoints, authentication, admin panel, and Stripe payment/connect flows.

## Core Responsibilities

- REST API for marketplace data and user actions (`/api/*`)
- Authentication with Laravel Sanctum
- Stripe Checkout, Stripe Connect onboarding, webhooks
- Orders, listings, products, auctions, draws, offers, messages, support
- Filament admin panel for moderation and operations (`/admin`)

## Stack

- PHP 8.1+
- Laravel 10
- Filament 3
- MySQL
- Stripe PHP SDK

## Requirements

- PHP 8.1+
- Composer
- MySQL
- Node.js 18+ (for Laravel/Vite assets when needed)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

## Important Environment Variables

Update `backend/.env` at least for:

- App and URLs: `APP_URL`, `FRONTEND_URL`
- Database: `DB_*`
- Stripe:
  - `STRIPE_SECRET`
  - `STRIPE_PUBLISHABLE_KEY`
  - `STRIPE_WEBHOOK_SECRET`
  - `STRIPE_CONNECT_COUNTRY`
  - `STRIPE_MARKETPLACE_COMMISSION_RATE`
  - `STRIPE_MARKETPLACE_BUYER_FEE_RATE`
- Auth links:
  - `AUTH_EMAIL_VERIFICATION_URL`
  - `AUTH_PASSWORD_RESET_URL`
- Google auth (optional):
  - `GOOGLE_CLIENT_ID`
  - `GOOGLE_CLIENT_SECRET`
  - `GOOGLE_REDIRECT_URI`
- DHL Express MyDHL API:
  - `DHL_API_ENABLED`
  - `DHL_API_USE_TEST_ENVIRONMENT`
  - `DHL_API_USERNAME`
  - `DHL_API_PASSWORD`
  - `DHL_API_ACCOUNT_NUMBER`
  - `DHL_API_DEFAULT_PRODUCT_CODE`
  - `DHL_API_DEFAULT_PACKAGE_WEIGHT_KG`
  - `DHL_AUTO_RELEASE_DAYS_AFTER_DELIVERY`

## DHL Notes

- The current DHL setup can run against the MyDHL sandbox by setting `DHL_API_USE_TEST_ENVIRONMENT=true`.
- Keep `DHL_API_ENABLED=false` until the provided DHL credentials are validated successfully against the selected environment.
- Do not point production orders at the live DHL environment until production MyDHL credentials are issued.
- Quote any DHL secret in `.env` when it contains special characters such as `#`.
- The sandbox account tested on Cardora currently accepts `DHL_API_DEFAULT_PRODUCT_CODE=T`; `N` was rejected by DHL for this payer/account combination.
- If BoxNow should remain unavailable for checkout and new listings, but still be selectable while editing an existing listing, set `BOXNOW_LISTING_EDIT_ENABLED=true` while keeping `BOXNOW_API_ENABLED=false`.
- Sellers who create DHL shipments must have `shipping_origin` profile fields filled in:
  - `full_name`
  - `phone`
  - `address_line_1`
  - `city`
  - `postal_code`
  - `country_code`

## Run (Development)

Start Laravel API:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

If you edit Laravel frontend assets (Filament/theme resources), also run:

```bash
npm install
npm run dev
```

## Routes

- API base: `/api`
- Webhook endpoint: `/api/stripe/webhooks`
- Admin panel: `/admin`

## Useful Commands

```bash
php artisan migrate
php artisan db:seed
php artisan test
php artisan optimize:clear
```

## Frontend Integration

This backend serves the SPA from `backend/public/index.html` through a fallback route for non-API requests.

After building frontend (`frontend/dist`), copy build files into `backend/public/`.
