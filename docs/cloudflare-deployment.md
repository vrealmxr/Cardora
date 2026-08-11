# Cloudflare Deployment Notes

## Current State

- Domain: `cardora.gr`
- Cloudflare zone status: active as of July 17, 2026
- Current origin: Hostinger shared hosting
- Backend stack: Laravel 10 + MySQL
- Frontend stack: React 18 + Vite SPA

## Recommended Architecture

### Option A: Keep current origin behind Cloudflare

Use Cloudflare as the public edge while keeping both frontend and backend served by the current origin.

- `cardora.gr` proxied through Cloudflare
- `www.cardora.gr` proxied through Cloudflare
- Laravel + built SPA stay on the existing origin
- Stripe and DHL continue to use the same backend endpoints

This is the safest short-term cutover because it does not require replatforming PHP or MySQL.

### Option B: Frontend on Cloudflare Pages, backend on dedicated origin

Use Cloudflare Pages only for the React SPA and keep Laravel on a separate server or VPS.

- `www.cardora.gr` or a dedicated frontend hostname served by Pages
- API served from origin backend, still behind Cloudflare proxy
- Requires additional Cloudflare account permissions for Pages project creation

This is cleaner long-term, but only makes sense if the backend origin is stable and intentionally separated.

### Option C: Backend via Cloudflare Tunnel

Recommended only if the backend runs on a VPS or machine where `cloudflared` can be installed and supervised.

- Not suitable for the current shared hosting environment
- Requires additional Cloudflare account permissions for Tunnel creation

## Current Cloudflare-safe Defaults Applied

- SSL mode: `Full`
- Always Use HTTPS: `On`
- Minimum TLS Version: `1.2`
- Brotli: `On`
- HTTP/3: `On`
- TLS 1.3: `On`

## DNS Notes

Mail-related CNAME records must remain `DNS only`, not proxied:

- `autoconfig`
- `autodiscover`
- `hostingermail-a._domainkey`
- `hostingermail-b._domainkey`
- `hostingermail-c._domainkey`

## Frontend Cloudflare Pages Readiness

The frontend now includes:

- `frontend/public/_redirects`
  - SPA fallback for client-side routing
- `frontend/public/_headers`
  - Immutable caching for hashed static assets
  - Basic security headers for Pages/static delivery
- `frontend/public/_worker.js`
  - proxies backend-dependent requests to `https://origin.cardora.gr`
  - keeps `/index.php/*`, `/api/*`, `/storage/*`, `/icons/*` and `/asset.php` working after the apex moves to Pages
- `frontend/wrangler.toml`
  - local Pages deployment config for repeatable deploys
- `frontend/.env.cloudflare-pages.example`
  - example env file for Pages builds that should target the live backend origin

## Environment Variables To Mirror During Migration

### Backend

- `APP_URL`
- `FRONTEND_URL`
- DB credentials
- Stripe keys and webhook secret
- DHL credentials and environment flags
- mail / SMTP credentials
- Google Maps or other third-party service keys

### Frontend

- `VITE_API_BASE_URL`
- `VITE_MARKETPLACE_BUYER_FEE_RATE`
- `VITE_GOOGLE_MAPS_API_KEY`

## Important Constraints

- Laravel + MySQL cannot be moved “as-is” into Cloudflare Pages
- Shared hosting is not a good target for Cloudflare Tunnel
- If we want a cleaner Cloudflare-native backend path later, move Laravel to a VPS first

## Next Practical Steps

1. Decide whether frontend stays on current origin or moves to Cloudflare Pages.
2. If using Pages, create a project and connect the frontend build:
   - build command: `npm run build`
   - output directory: `dist`
3. Keep backend on origin behind Cloudflare proxy.
4. Recheck Stripe webhook endpoint after any hostname or routing change.
5. Recheck DHL callbacks / sync endpoints after final DNS shape is locked.

## Current Pages Preview

- Pages project: `cardora-frontend`
- Preview deployment URL from July 17, 2026:
  - `https://12d5f98d.cardora-frontend.pages.dev`

Builds intended for Pages should use:

- the default relative API path (`/index.php/api`) when the apex domain is served by Pages + worker proxy

## Domain Cutover Notes

- `origin.cardora.gr` now points to the current Hostinger origin and remains `DNS only`
- `cardora.gr` now points to `cardora-frontend.pages.dev`
- `www.cardora.gr` now points to `cardora-frontend.pages.dev`
- as of July 17, 2026:
  - `www.cardora.gr` is active on Pages
  - `cardora.gr` has active DNS verification and is waiting for final HTTP validation / certificate propagation

## Backend Proxy Path

- Hostinger does not currently serve the Cardora Laravel app directly on arbitrary extra subdomains like `origin.cardora.gr`
- a clean Cloudflare-compatible backend path is to serve a dedicated proxy hostname such as `api.cardora.gr`
- the proxy should forward requests to the live Hostinger-backed `https://cardora.gr`
- implementation scaffold lives in:
  - `cloudflare/backend-proxy/wrangler.toml`
  - `cloudflare/backend-proxy/src/worker.js`
- Pages-compatible deployable proxy lives in:
  - `cloudflare/backend-proxy-pages/public/_worker.js`
  - `cloudflare/backend-proxy-pages/public/index.html`
- current live test result on July 17, 2026:
  - `https://api.cardora.gr/index.php/api/bootstrap` responds successfully through the proxy

Frontend builds intended for Cloudflare Pages should now use:

- `VITE_API_BASE_URL=https://api.cardora.gr/index.php/api`
