# Cardora

## Live scanner integration — 2026-10-20

Canonical public scanner: https://cardora.gr/el/cardora-scanner (also `/en/cardora-scanner`). The existing `CardoraScannerPage` now uses the deployed `/index.php/api/card-scans` backend for uploads, polling, results, opt-in training collection and owner deletion. It reuses `BinderShell`, `Button`, `CardSurface` and `Select`; existing white/gold colors, global CSS, logo, navigation and other pages were preserved. Placeholder generated prices were removed; pricing is explicitly coming later. `/scanner` redirects to the canonical Greek URL.

This worktree, `D:\CardoraGit\Cardora-scanner-live`, branch `codex/cardora-scanner-live`, is the current frontend integration source. Base commit `97347f3` reproduces the previous live JavaScript/CSS byte-for-byte; the previous deployment's Git metadata incorrectly reflected the preceding commit with dirty files. Only `frontend/src/pages/CardoraScannerPage.jsx` changed in the frontend. Do not rebuild the live frontend from the older checkout at `D:\CardoraGit\Cardora`.

The new Laravel scanner/backend feature source remains in `D:\CardoraGit\Cardora`; its README documents private storage, admin review, server environment, quotas, retention and rollback. Do not deploy this worktree's older backend wholesale. Admin review is still https://cardora.gr/admin/dataset-review, protected by existing admin login. RunPod configuration was not changed and no inference requests were made for this UI change.

Cloudflare Pages project: `cardora-frontend`. Verified preview: `8e8ac3c6.cardora-frontend.pages.dev`; production deployment: `0b6b488d.cardora-frontend.pages.dev`. Previous production rollback ID: `432ed9f9-d4b0-499e-a3f5-95288926701c`. Deploy only the built frontend with the project's existing Wrangler configuration. The private Hostinger backup for the old `/scanner` route is `/home/u912666299/cardora-private/deploy-public-scans-20261020/card_scans_web.before-canonical-redirect.php`.

Validation: page ESLint, Vite production build, live Greek/English routes, original-URL redirect, unchanged admin login protection, visual preview/live comparison. A loopback-only browser fixture verified front/back NBA upload, explicit opt-in, one POST, result polling with unresolved parallel, and deletion; it did not call RunPod. Evidence: `D:\Cardora\RuntimeValidation\scanner-existing-page-20261020`. Temporary fixture server was stopped. Live signed-in browser scanning was not repeated, to avoid another paid inference.

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
