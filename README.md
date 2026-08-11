# Cardora

Cardora is a collector-focused marketplace with protected checkout flows, seller onboarding, auctions, draws, private offers, and profile/community features.

This repository contains the full stack:

- `frontend/` - React + Vite SPA
- `backend/` - Laravel API + Filament Admin + Stripe integrations
- `backend/public/` - deployment target for the built SPA

## Tech Stack

- Frontend: React 18, Vite, Tailwind CSS
- Backend: Laravel 10, Sanctum, Filament 3
- Payments: Stripe Checkout + Stripe Connect
- Database: MySQL

## Local Setup (Full Project)

### 1) Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Set your backend `.env` values (especially DB + Stripe keys + URLs).

Run the API:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

### 2) Frontend

```bash
cd frontend
npm install
```

Create `frontend/.env.local`:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
VITE_MARKETPLACE_BUYER_FEE_RATE=0.00
VITE_GOOGLE_MAPS_API_KEY=
```

Run the SPA:

```bash
npm run dev
```

Default local URLs:

- Frontend: `http://127.0.0.1:5173`
- Backend API: `http://127.0.0.1:8000/api`
- Filament Admin: `http://127.0.0.1:8000/admin`

## Build / Deployment Notes

Production SPA assets are served from `backend/public`.

Build frontend:

```bash
cd frontend
npm run build
```

Then copy the generated `frontend/dist/*` into `backend/public/` (including `static/` and `index.html`).

Example (PowerShell):

```powershell
Copy-Item -Path .\frontend\dist\* -Destination .\backend\public\ -Recurse -Force
```

## Cloudflare Notes

- The frontend is prepared for Cloudflare Pages-style SPA hosting with:
  - `frontend/public/_redirects`
  - `frontend/public/_headers`
- See [docs/cloudflare-deployment.md](/Users/vrealm/Documents/GitHub/Cardora/docs/cloudflare-deployment.md) for the recommended Cloudflare deployment paths and migration constraints.

## Quality Checks

- Frontend lint:

```bash
cd frontend
npm run lint
```

- Backend tests:

```bash
cd backend
php artisan test
```
