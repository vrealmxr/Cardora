#!/usr/bin/env bash
#
# Cardora deploy script — builds the frontend locally and rsyncs artifacts
# + backend PHP source to the Hostinger shared-hosting origin over SSH.
#
# Usage:
#   scripts/deploy.sh [options]
#
# Options:
#   --skip-frontend         Don't build/sync the frontend at all
#   --skip-backend          Don't sync/composer-install the backend
#   --skip-build            Skip `npm run build` (reuse existing frontend/dist)
#   --with-env              Also sync backend/.env(.production) to the server
#                           (off by default — server .env is left untouched)
#   --migrate               Run `php artisan migrate --force` after deploy
#   --dry-run               Pass --dry-run to rsync, skip remote mutating cmds
#   -h, --help              Show this help
#
set -euo pipefail

# ---- config -----------------------------------------------------------
SSH_KEY="$HOME/.ssh/cardora_deploy"
REMOTE_HOST="89.116.53.170"
REMOTE_PORT="65002"
REMOTE_USER="u912666299"
REMOTE_ROOT="/home/u912666299/domains/cardora.gr/public_html"
REMOTE_PHP="/opt/alt/php81/usr/bin/php"
REMOTE_COMPOSER="/usr/local/bin/composer"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# ---- flags --------------------------------------------------------------
SKIP_FRONTEND=0
SKIP_BACKEND=0
SKIP_BUILD=0
WITH_ENV=0
RUN_MIGRATE=0
DRY_RUN=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-frontend) SKIP_FRONTEND=1 ;;
    --skip-backend) SKIP_BACKEND=1 ;;
    --skip-build) SKIP_BUILD=1 ;;
    --with-env) WITH_ENV=1 ;;
    --migrate) RUN_MIGRATE=1 ;;
    --dry-run) DRY_RUN=1 ;;
    -h|--help) grep '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
  shift
done

SSH_OPTS=(-i "$SSH_KEY" -p "$REMOTE_PORT" -o BatchMode=yes)
RSYNC_SSH="ssh ${SSH_OPTS[*]}"
RSYNC_FLAGS=(-az --human-readable)
[[ $DRY_RUN -eq 1 ]] && RSYNC_FLAGS+=(--dry-run -v)

remote() {
  ssh "${SSH_OPTS[@]}" "${REMOTE_USER}@${REMOTE_HOST}" "$@"
}

echo "==> Cardora deploy starting ($(date '+%Y-%m-%d %H:%M:%S'))"
[[ $DRY_RUN -eq 1 ]] && echo "    (dry-run mode — no remote changes will be made)"

# ---- 1. Frontend: build locally, land output in backend/public --------
if [[ $SKIP_FRONTEND -eq 0 ]]; then
  if [[ $SKIP_BUILD -eq 0 ]]; then
    echo "==> Building frontend (npm run build)"
    (cd "$REPO_ROOT/frontend" && npm run build)
  else
    echo "==> Skipping frontend build (--skip-build), reusing frontend/dist"
  fi

  if [[ ! -d "$REPO_ROOT/frontend/dist" ]]; then
    echo "!! frontend/dist not found — build must run at least once" >&2
    exit 1
  fi

  echo "==> Syncing frontend/dist -> local backend/public (index.html + static/)"
  cp "$REPO_ROOT/frontend/dist/index.html" "$REPO_ROOT/backend/public/index.html"
  rsync -a --delete "$REPO_ROOT/frontend/dist/static/" "$REPO_ROOT/backend/public/static/"

  echo "==> Syncing root front-controller files to server (public_html/)"
  rsync "${RSYNC_FLAGS[@]}" -e "$RSYNC_SSH" \
    "$REPO_ROOT/index.php" \
    "$REPO_ROOT/asset.php" \
    "$REPO_ROOT/app.js.php" \
    "$REPO_ROOT/app.css.php" \
    "$REPO_ROOT/.htaccess" \
    "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_ROOT}/"

  echo "==> Syncing built SPA (backend/public/index.html + static/) to server"
  rsync "${RSYNC_FLAGS[@]}" \
    "$REPO_ROOT/backend/public/index.html" \
    -e "$RSYNC_SSH" \
    "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_ROOT}/backend/public/index.html"
  rsync "${RSYNC_FLAGS[@]}" --delete \
    -e "$RSYNC_SSH" \
    "$REPO_ROOT/backend/public/static/" \
    "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_ROOT}/backend/public/static/"
else
  echo "==> Skipping frontend (--skip-frontend)"
fi

# ---- 2. Backend: sync PHP source, composer install remotely -----------
if [[ $SKIP_BACKEND -eq 0 ]]; then
  echo "==> Syncing backend PHP source to server"

  EXCLUDES=(
    --exclude '.git'
    --exclude '.DS_Store'
    --exclude 'node_modules'
    --exclude 'vendor'
    --exclude '.phpunit.cache'
    --exclude '.phpunit.result.cache'
    --exclude 'storage/'
    --exclude 'public/'          # handled separately above (index.html/static only)
    --exclude 'bootstrap/cache/*.php'
  )
  if [[ $WITH_ENV -eq 0 ]]; then
    EXCLUDES+=(--exclude '.env' --exclude '.env.backup' --exclude '.env.production')
  fi

  rsync "${RSYNC_FLAGS[@]}" "${EXCLUDES[@]}" \
    -e "$RSYNC_SSH" \
    "$REPO_ROOT/backend/" \
    "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_ROOT}/backend/"

  if [[ $WITH_ENV -eq 1 ]]; then
    echo "    (--with-env) backend/.env was included in the sync above"
  fi

  if [[ $DRY_RUN -eq 0 ]]; then
    echo "==> Running composer install on server (PHP 8.1)"
    remote "cd ${REMOTE_ROOT}/backend && ${REMOTE_PHP} ${REMOTE_COMPOSER} install --no-dev --optimize-autoloader --no-interaction"

    echo "==> Refreshing Laravel caches on server"
    remote "cd ${REMOTE_ROOT}/backend && ${REMOTE_PHP} artisan config:cache && ${REMOTE_PHP} artisan route:cache && ${REMOTE_PHP} artisan view:cache"

    if [[ $RUN_MIGRATE -eq 1 ]]; then
      echo "==> Running database migrations (--migrate)"
      remote "cd ${REMOTE_ROOT}/backend && ${REMOTE_PHP} artisan migrate --force"
    fi
  else
    echo "    (dry-run) skipping composer install / artisan cache / migrate"
  fi
else
  echo "==> Skipping backend (--skip-backend)"
fi

echo "==> Deploy finished ($(date '+%Y-%m-%d %H:%M:%S'))"
