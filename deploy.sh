#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────
# deploy.sh — build & deploy the login e-learning platform
#
#   ./deploy.sh staging              build + deploy the staging stack
#   ./deploy.sh production           build + deploy the production stack
#
# Options:
#   --skip-checks   skip the frontend type-check / lint step
#   --no-cache      build images from scratch (use if the Docker build
#                   cache misbehaves — see README, "Docker build cache")
#
# The code is taken from the current working tree (you edit on the
# server). Each environment runs from its own baked images, its own
# database and its own upload storage, so deploying to staging never
# touches production. A failing step aborts BEFORE the running stack is
# replaced.
# ─────────────────────────────────────────────────────────────────────
set -euo pipefail

cd "$(dirname "$0")"

# ── Parse arguments ──────────────────────────────────────────────────
TARGET=""
SKIP_CHECKS=false
NO_CACHE=""
for arg in "$@"; do
  case "$arg" in
    staging|production) TARGET="$arg" ;;
    --skip-checks)      SKIP_CHECKS=true ;;
    --no-cache)         NO_CACHE="--no-cache" ;;
    *) echo "Unknown argument: $arg" >&2; exit 1 ;;
  esac
done

if [ -z "$TARGET" ]; then
  echo "Usage: ./deploy.sh {staging|production} [--skip-checks] [--no-cache]" >&2
  exit 1
fi

command -v docker >/dev/null || { echo "ERROR: docker is not installed" >&2; exit 1; }

ENV_FILE="$TARGET/.env"
OVERLAY="$TARGET/docker-compose.$TARGET.yml"

if [ ! -f "$ENV_FILE" ]; then
  echo "ERROR: $ENV_FILE is missing." >&2
  echo "       Copy $TARGET/.env.example to $ENV_FILE and fill in the secrets." >&2
  exit 1
fi

# docker compose wrapper, bound to the chosen environment
dc() {
  docker compose \
    -f docker-compose.yml \
    -f "$OVERLAY" \
    --env-file "$ENV_FILE" \
    "$@"
}

# read a single value out of the environment file
env_get() { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- || true; }

log() { printf '\n\033[1;36m▶ %s\033[0m\n' "$1"; }

echo "═════════════════════════════════════════════"
echo "  Deploying to : $TARGET"
echo "  Project      : $(env_get COMPOSE_PROJECT_NAME)"
if [ -d .git ]; then
  echo "  Git commit   : $(git rev-parse --short HEAD 2>/dev/null || echo '(no commits yet)')"
  if ! git diff --quiet 2>/dev/null || ! git diff --cached --quiet 2>/dev/null; then
    echo "  Git state    : uncommitted changes in the working tree"
  fi
fi
echo "═════════════════════════════════════════════"

# ── 1. Pre-deploy checks (frontend type-check + lint) ────────────────
if [ "$SKIP_CHECKS" = true ]; then
  log "Skipping checks (--skip-checks)"
else
  log "Checking the frontend (type-check + lint)"
  # Build just the dependency/source stage and run the checks inside it,
  # so a type error fails fast — before the slow production build.
  docker build $NO_CACHE \
    --target base \
    -t login-elearning-checks:"$TARGET" \
    -f docker/frontend/Dockerfile \
    ./frontend
  docker run --rm login-elearning-checks:"$TARGET" \
    sh -c "npm run type-check && npm run lint"
  echo "  frontend checks passed"
  echo "  backend: no test suite configured — skipping"
fi

# ── 2. Build the images ──────────────────────────────────────────────
log "Building images"
dc build $NO_CACHE

# ── 3. Start / update the stack ──────────────────────────────────────
log "Starting the $TARGET stack"
dc up -d --remove-orphans

# ── 4. Wait for the database ─────────────────────────────────────────
log "Waiting for the database"
for i in $(seq 1 30); do
  health="$(dc ps database --format '{{.Health}}' 2>/dev/null || true)"
  [ "$health" = "healthy" ] && { echo "  database healthy"; break; }
  [ "$i" -eq 30 ] && { echo "ERROR: database did not become healthy in time" >&2; exit 1; }
  sleep 2
done

# wait until the backend can run a console command
for i in $(seq 1 30); do
  dc exec -T --user www-data backend php bin/console --version >/dev/null 2>&1 && break
  [ "$i" -eq 30 ] && { echo "ERROR: backend is not responding" >&2; exit 1; }
  sleep 2
done

# ── 5. Database migrations ───────────────────────────────────────────
log "Running database migrations"
# Run as www-data (the php-fpm user) so any cache the kernel warms is
# owned by www-data — not root, which php-fpm could not then rewrite.
dc exec -T --user www-data backend php bin/console doctrine:migrations:migrate \
  --no-interaction --allow-no-migration

# ── 6. Health check ──────────────────────────────────────────────────
log "Health check"
HOST="$(env_get DEFAULT_SNI)"; [ -z "$HOST" ] && HOST="localhost"
HTTPS_PORT="$(env_get HTTPS_PORT)"; [ -z "$HTTPS_PORT" ] && HTTPS_PORT="443"

probe() {  # probe <path> → prints the HTTP status code (000 if the request never landed)
  local code
  code="$(curl -sk -o /dev/null -w '%{http_code}' --max-time 5 \
    --resolve "$HOST:$HTTPS_PORT:127.0.0.1" \
    "https://$HOST:$HTTPS_PORT$1" 2>/dev/null)" || code="000"
  printf '%s' "${code:-000}"
}

# Retry the health check for up to ~20 s. On a fresh domain Caddy is
# still obtaining its Let's Encrypt cert just after `up`, and TLS
# handshakes from localhost can briefly fail before the new cert loads.
healthy=false
for attempt in $(seq 1 10); do
  front_code="$(probe /)"
  api_code="$(probe /api/courses)"

  ok=true
  case "$front_code" in 200|304) ;; *) ok=false ;; esac
  # Any non-5xx, non-000 reply means the Symfony kernel booted (401/404 ok).
  case "$api_code" in 5*|000|"") ok=false ;; esac

  if [ "$ok" = true ]; then healthy=true; break; fi
  [ "$attempt" -lt 10 ] && sleep 2
done

echo "  frontend  /            -> HTTP $front_code"
echo "  API       /api/courses -> HTTP $api_code"

if [ "$healthy" != true ]; then
  echo >&2
  echo "ERROR: health check failed — the $TARGET stack is running but not" >&2
  echo "       serving correctly. Inspect the backend logs:" >&2
  echo "       docker compose -f docker-compose.yml -f $OVERLAY --env-file $ENV_FILE logs backend" >&2
  exit 1
fi
echo "  health check passed"

# ── Done ─────────────────────────────────────────────────────────────
log "Deploy to $TARGET complete"
echo
dc ps
echo
echo "  $TARGET is live at:  $(env_get SITE_ADDRESS):$HTTPS_PORT"
