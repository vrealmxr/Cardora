# Cardora Frontend

React + Vite single-page application for Cardora marketplace.

## Features (Frontend)

- Marketplace browsing for products/listings
- Auth flows (email + Google redirect flow)
- Checkout flow with Stripe redirect
- Profile, seller dashboard, messages, support, draws
- Bilingual UI support (Greek/English)

## Stack

- React 18
- Vite 5
- Tailwind CSS
- React Router 6

## Requirements

- Node.js 18+
- npm

## Install

```bash
npm install
```

## Environment

Create `frontend/.env.local`:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
VITE_MARKETPLACE_BUYER_FEE_RATE=0.00
VITE_GOOGLE_MAPS_API_KEY=
```

Notes:

- `VITE_API_BASE_URL` should point to Cardora backend API.
- If your backend setup requires it, you can use `http://127.0.0.1:8000/index.php/api`.

## Run

```bash
npm run dev
```

App URL (default): `http://127.0.0.1:5173`

## Scripts

- `npm run dev` - start Vite dev server
- `npm run build` - production build to `dist/`
- `npm run preview` - preview production build
- `npm run lint` - run ESLint

## Build Output and Deployment

Frontend build output is generated in `frontend/dist`.

For integrated deployment, copy `dist/*` into `backend/public/` so Laravel can serve the SPA:

```powershell
Copy-Item -Path .\dist\* -Destination ..\backend\public\ -Recurse -Force
```
