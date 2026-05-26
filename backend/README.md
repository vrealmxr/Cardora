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
