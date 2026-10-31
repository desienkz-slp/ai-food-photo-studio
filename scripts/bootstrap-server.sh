#!/usr/bin/env bash
set -Eeuo pipefail

# Explicit server bootstrap. Never invoked by git clone.
# Usage: sudo ./scripts/bootstrap-server.sh --install [--repo URL] [--ref main]
# DRY_RUN=1 prints the plan without changing the host.

usage() {
  cat <<'EOF'
Usage: sudo ./scripts/bootstrap-server.sh --install [options]

Options:
  --repo URL       Git repository (default: public project repository)
  --ref REF        Git branch/tag/commit (default: main)
  --release ID     Release directory name (default: UTC timestamp)
  --install        Required explicit mutation flag
  --dry-run        Validate and print the plan only

Environment:
  APP_ROOT          default: /opt/food-photo-studio
  DRY_RUN=1         same as --dry-run

This script installs OS packages and application dependencies, clones into a
new release directory, writes systemd unit files, and enables services. It
never creates .env, generates secrets, runs migrations, or prints secrets.
Review the script and provide external configuration before activation.
EOF
}

repo="https://github.com/desienkz-slp/ai-food-photo-studio.git"
ref="main"
release="$(date -u +%Y%m%d%H%M%S)"
install=0
dry_run="${DRY_RUN:-0}"
app_root="${APP_ROOT:-/opt/food-photo-studio}"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) repo="$2"; shift 2;;
    --ref) ref="$2"; shift 2;;
    --release) release="$2"; shift 2;;
    --install) install=1; shift;;
    --dry-run) dry_run=1; shift;;
    -h|--help) usage; exit 0;;
    *) printf 'Unknown option: %s\n' "$1" >&2; usage >&2; exit 2;;
  esac
done
[[ "$install" == 1 ]] || { usage >&2; exit 2; }
[[ "$dry_run" == 0 || "$dry_run" == 1 ]] || { echo 'DRY_RUN must be 0 or 1' >&2; exit 2; }
[[ "$release" =~ ^[A-Za-z0-9._-]+$ ]] || { echo 'Invalid release name' >&2; exit 2; }

if [[ "$dry_run" == 0 && "$(id -u)" != 0 ]]; then
  echo 'Run as root for host bootstrap, or use --dry-run.' >&2; exit 1
fi

run() { if [[ "$dry_run" == 1 ]]; then printf '+ %q' "$@"; printf '\n'; else "$@"; fi; }
write_unit() {
  local name="$1" content="$2"
  if [[ "$dry_run" == 1 ]]; then echo "+ write /etc/systemd/system/$name.service"; return; fi
  printf '%s\n' "$content" > "/etc/systemd/system/$name.service"
}

if command -v apt-get >/dev/null 2>&1; then
  run apt-get update
  run env DEBIAN_FRONTEND=noninteractive apt-get install -y git curl ca-certificates unzip nginx php8.3-fpm php8.3-cli php8.3-gd php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip composer nodejs npm
else
  echo 'Unsupported package manager: expected apt-get (Debian/Ubuntu).' >&2; exit 1
fi

run install -d -m 0755 "$app_root/releases" "$app_root/shared"
release_dir="$app_root/releases/$release"
run git clone --branch "$ref" "$repo" "$release_dir"
run "$release_dir/scripts/install-release.sh" --install

write_unit food-photo-backend '[Unit]
Description=AI Food Photo Studio Laravel backend
After=network.target
[Service]
Type=simple
WorkingDirectory='"$release_dir"'/backend
ExecStart=/usr/bin/php artisan serve --host=127.0.0.1 --port=8000
Restart=on-failure
User=www-data
[Install]
WantedBy=multi-user.target'
write_unit food-photo-frontend '[Unit]
Description=AI Food Photo Studio Next.js frontend
After=network.target
[Service]
Type=simple
WorkingDirectory='"$release_dir"'/frontend
ExecStart=/usr/bin/npm run start -- --hostname 127.0.0.1 --port 3000
Restart=on-failure
User=www-data
[Install]
WantedBy=multi-user.target'

if [[ "$dry_run" == 1 ]]; then
  echo '+ nginx reverse proxy configuration (not written in dry-run)'
  echo '+ systemctl daemon-reload enable --now food-photo-backend food-photo-frontend nginx'
  echo 'DRY RUN complete; no host changes made.'
  exit 0
fi

# Production .env, database, storage, Nginx config and migrations remain explicit gates.
[[ -f "$release_dir/backend/.env" ]] || { echo 'Missing backend/.env; refusing service activation.' >&2; exit 1; }
run systemctl daemon-reload
run systemctl enable --now food-photo-backend food-photo-frontend nginx
printf '%s\n' "Release cloned at $release_dir" 'Services enabled; migrations were not run.'
