#!/usr/bin/env bash
set -Eeuo pipefail

# One-file native installer/activator for Debian/Ubuntu.
# Explicitly run after reviewing the release. Never run from git hooks.
# Required external config: /etc/food-photo-studio/backend.env
# Optional: RUN_MIGRATIONS=1 to approve database migrations explicitly.

REPO_URL="${REPO_URL:-https://github.com/desienkz-slp/ai-food-photo-studio.git}"
REPO_REF="${REPO_REF:-main}"
APP_ROOT="${APP_ROOT:-/opt/food-photo-studio}"
RELEASE_ID="${RELEASE_ID:-$(date -u +%Y%m%d%H%M%S)}"
DOMAIN="${DOMAIN:-172.18.20.148}"
PUBLIC_PORT="${PUBLIC_PORT:-8089}"
BACKEND_PORT="${BACKEND_PORT:-8000}"
FRONTEND_PORT="${FRONTEND_PORT:-3000}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"
DRY_RUN="${DRY_RUN:-0}"
ENV_FILE="${ENV_FILE:-/etc/food-photo-studio/backend.env}"
RELEASE_DIR="$APP_ROOT/releases/$RELEASE_ID"
CURRENT_LINK="$APP_ROOT/current"

log() { printf '[food-photo-studio] %s\n' "$*"; }
fail() { printf '[food-photo-studio] ERROR: %s\n' "$*" >&2; exit 1; }
run() { if [[ "$DRY_RUN" == 1 ]]; then printf '+ '; printf '%q ' "$@"; printf '\n'; else "$@"; fi; }
write_file() { local path="$1"; shift; if [[ "$DRY_RUN" == 1 ]]; then log "would write $path"; else install -D -m 0644 /dev/stdin "$path" <<<"$*"; fi; }

usage() {
  cat <<EOF
Usage: sudo ./scripts/install-and-activate.sh [--dry-run]

Environment:
  REPO_URL, REPO_REF, APP_ROOT, RELEASE_ID, DOMAIN
  ENV_FILE (default: /etc/food-photo-studio/backend.env)
  RUN_MIGRATIONS=1 (explicitly permits php artisan migrate --force)
  DRY_RUN=1 (no host changes)
EOF
}
[[ "${1:-}" != "--help" ]] || { usage; exit 0; }
[[ "$DRY_RUN" == 0 || "$DRY_RUN" == 1 ]] || fail 'DRY_RUN must be 0 or 1'
[[ "$RUN_MIGRATIONS" == 0 || "$RUN_MIGRATIONS" == 1 ]] || fail 'RUN_MIGRATIONS must be 0 or 1'
[[ "$RELEASE_ID" =~ ^[A-Za-z0-9._-]+$ ]] || fail 'invalid RELEASE_ID'
if [[ "$DRY_RUN" == 0 && "$(id -u)" != 0 ]]; then fail 'run as root or use DRY_RUN=1'; fi

if command -v apt-get >/dev/null 2>&1; then
  # Some hosts advertise IPv6 but have no working route, which hangs apt and
  # add-apt-repository against ppa.launchpadcontent.net. Force IPv4 for apt.
  if [[ "$DRY_RUN" == 0 ]]; then
    printf 'Acquire::ForceIPv4 "true";\n' > /etc/apt/apt.conf.d/99force-ipv4
  fi
  run env DEBIAN_FRONTEND=noninteractive apt-get update
  # Base tooling needed to add third-party repositories reliably.
  run env DEBIAN_FRONTEND=noninteractive apt-get install -y \
    git curl ca-certificates unzip gnupg lsb-release software-properties-common

  # PHP 8.3 is not in Ubuntu 22.04 base repos; add Ondrej PPA when absent.
  # add-apt-repository uses launchpadlib (Python API) which ignores apt's IPv4
  # setting and hangs on broken-IPv6 hosts; add the PPA manually instead.
  ONDREJ_KEY='14AA40EC0831756756D7F66C4F4EA0AAE5267A6C'
  add_ondrej_ppa() {
    local codename; codename="$(lsb_release -cs)"
    run install -d -m 0755 /etc/apt/keyrings
    run bash -c "curl -4 -fsSL 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x${ONDREJ_KEY}' | gpg --dearmor -o /etc/apt/keyrings/ondrej-php.gpg"
    run bash -c "printf 'deb [signed-by=/etc/apt/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu %s main\n' '${codename}' > /etc/apt/sources.list.d/ondrej-php.list"
    run env DEBIAN_FRONTEND=noninteractive apt-get update
  }
  if [[ "$DRY_RUN" == 0 ]] && ! php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
    add_ondrej_ppa
  elif [[ "$DRY_RUN" == 1 ]]; then
    add_ondrej_ppa
  fi

  # Node.js 18+ is required by Next.js; Ubuntu 22.04 ships Node 12. Add NodeSource 20 when too old.
  node_major="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
  if [[ "$DRY_RUN" == 1 || "${node_major:-0}" -lt 18 ]]; then
    # Distro node 12 (libnode-dev/nodejs-doc) owns files that collide with the
    # NodeSource package; purge them first to avoid dpkg overwrite errors.
    if [[ "$DRY_RUN" == 0 ]]; then
      run env DEBIAN_FRONTEND=noninteractive apt-get purge -y libnode-dev nodejs-doc nodejs npm || true
      run env DEBIAN_FRONTEND=noninteractive apt-get autoremove -y || true
    fi
    run bash -c 'curl -fsSL https://deb.nodesource.com/setup_20.x | env DEBIAN_FRONTEND=noninteractive bash -'
  fi

  run env DEBIAN_FRONTEND=noninteractive apt-get install -y \
    nginx php8.3-fpm php8.3-cli php8.3-gd php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-sqlite3 php8.3-bcmath php8.3-intl nodejs
  # Composer installed via official installer to avoid distro version skew.
  if [[ "$DRY_RUN" == 0 ]] && ! command -v composer >/dev/null 2>&1; then
    run bash -c 'curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer'
  fi
else
  [[ "$DRY_RUN" == 1 ]] || fail 'Debian/Ubuntu apt-get is required'
  log 'apt-get not found locally; dry-run only'
fi

run install -d -m 0755 "$APP_ROOT/releases" "$APP_ROOT/shared" "$APP_ROOT/shared/database" /etc/food-photo-studio

# Disable the legacy Python/uvicorn prototype if present so it cannot bind ports.
if [[ "$DRY_RUN" == 0 ]] && systemctl list-unit-files 2>/dev/null | grep -q '^food-photo-studio\.service'; then
  run systemctl disable --now food-photo-studio.service || true
fi

run git clone --branch "$REPO_REF" "$REPO_URL" "$RELEASE_DIR"
run git -C "$RELEASE_DIR" rev-parse HEAD

# Auto-generate production backend env on first install; never overwrite an existing one.
if [[ "$DRY_RUN" == 0 ]]; then
  SQLITE_DB="$APP_ROOT/shared/database/database.sqlite"
  touch "$SQLITE_DB"
  chown www-data:www-data "$SQLITE_DB"
  chmod 0664 "$SQLITE_DB"
  if [[ ! -f "$ENV_FILE" ]]; then
    log "generating $ENV_FILE from .env.example"
    sed \
      -e 's#^APP_ENV=.*#APP_ENV=production#' \
      -e 's#^APP_DEBUG=.*#APP_DEBUG=false#' \
      -e "s#^APP_URL=.*#APP_URL=http://$DOMAIN:$PUBLIC_PORT#" \
      -e 's#^LOG_LEVEL=.*#LOG_LEVEL=warning#' \
      -e 's#^DB_CONNECTION=.*#DB_CONNECTION=sqlite#' \
      "$RELEASE_DIR/backend/.env.example" > "$ENV_FILE"
    printf 'DB_DATABASE=%s\n' "$SQLITE_DB" >> "$ENV_FILE"
    # APP_KEY must be generated against this env file.
    (cd "$RELEASE_DIR/backend" && set -a && . "$ENV_FILE" && set +a && php artisan key:generate --show) \
      | sed 's#[&/\]#\\&#g' | { read -r appkey; sed -i "s#^APP_KEY=.*#APP_KEY=${appkey}#" "$ENV_FILE"; }
  fi
  chmod 0640 "$ENV_FILE"
  chown root:www-data "$ENV_FILE"
fi

run "$RELEASE_DIR/scripts/install-release.sh" --install

# Migrations run by default; set RUN_MIGRATIONS=0 to skip explicitly.
if [[ "$RUN_MIGRATIONS" == 1 ]]; then
  run bash -c "cd '$RELEASE_DIR/backend' && set -a && . '$ENV_FILE' && set +a && php artisan migrate --force"
fi

# Laravel writes to storage/ and bootstrap/cache/; www-data must own the release tree.
if [[ "$DRY_RUN" == 0 ]]; then
  chown -R www-data:www-data "$RELEASE_DIR"
  find "$RELEASE_DIR/backend/storage" "$RELEASE_DIR/backend/bootstrap/cache" -type d -exec chmod 0775 {} + 2>/dev/null || true
fi

backend_unit="[Unit]
Description=AI Food Photo Studio Laravel API
After=network.target
[Service]
Type=simple
WorkingDirectory=$RELEASE_DIR/backend
EnvironmentFile=$ENV_FILE
ExecStart=/usr/bin/php8.3 artisan serve --host=127.0.0.1 --port=$BACKEND_PORT
Restart=on-failure
RestartSec=5
User=www-data
[Install]
WantedBy=multi-user.target"
frontend_unit="[Unit]
Description=AI Food Photo Studio Next.js UI
After=network.target
[Service]
Type=simple
WorkingDirectory=$RELEASE_DIR/frontend
Environment=NODE_ENV=production
ExecStart=/usr/bin/npm run start -- --hostname 127.0.0.1 --port $FRONTEND_PORT
Restart=on-failure
RestartSec=5
User=www-data
[Install]
WantedBy=multi-user.target"
nginx_conf="server {
    listen $PUBLIC_PORT default_server;
    server_name $DOMAIN _;
    client_max_body_size 25M;
    location /api/ {
        proxy_pass http://127.0.0.1:$BACKEND_PORT;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
    location /sanctum/ { proxy_pass http://127.0.0.1:$BACKEND_PORT; proxy_set_header Host \$host; }
    location / {
        proxy_pass http://127.0.0.1:$FRONTEND_PORT;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \"upgrade\";
    }
}"
write_file /etc/systemd/system/food-photo-backend.service "$backend_unit"
write_file /etc/systemd/system/food-photo-frontend.service "$frontend_unit"
write_file /etc/nginx/sites-available/food-photo-studio "$nginx_conf"
run ln -sfn /etc/nginx/sites-available/food-photo-studio /etc/nginx/sites-enabled/food-photo-studio
run rm -f /etc/nginx/sites-enabled/default
run nginx -t
run ln -sfn "$RELEASE_DIR" "$CURRENT_LINK"
run systemctl daemon-reload
run systemctl enable --now food-photo-backend food-photo-frontend nginx

if [[ "$DRY_RUN" == 1 ]]; then
  log 'dry-run complete; no host changes made'
else
  log "release active at $CURRENT_LINK"
  log 'health checks: curl -fsS http://127.0.0.1/ and curl -fsS http://127.0.0.1:8000/up'
  log 'migrations were not run unless RUN_MIGRATIONS=1 was supplied'
fi
