#!/usr/bin/env bash
set -euo pipefail

usage() {
  printf '%s\n' 'Usage: ./scripts/install-release.sh --install' 'Set DRY_RUN=1 to validate files and print the plan without executing dependency tools.'
}

if [[ $# -ne 1 || "$1" != "--install" ]]; then
  usage >&2
  exit 2
fi
case "${DRY_RUN:-0}" in
  0|1) ;;
  *) printf '%s\n' 'DRY_RUN must be 0 or 1.' >&2; exit 2 ;;
esac
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
for file in backend/composer.json backend/composer.lock frontend/package.json frontend/package-lock.json; do
  if [[ ! -f "$root/$file" ]]; then
    printf 'Missing required release file: %s\n' "$file" >&2
    exit 1
  fi
done
for command in php composer node npm; do
  if ! command -v "$command" >/dev/null 2>&1; then
    printf 'Missing prerequisite: %s\n' "$command" >&2
    exit 1
  fi
done
if [[ "${DRY_RUN:-0}" == 1 ]]; then
  printf '%s\n' 'DRY RUN: prerequisites found; versions and PHP extensions are not checked.' \
    'backend: composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-plugins' \
    'frontend: npm ci --ignore-scripts' 'frontend: npm run build'
  exit 0
fi
php -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("gd") ? 0 : 1);' || {
  printf '%s\n' 'PHP 8.4+ and the GD extension are required.' >&2
  exit 1
}
# Composer validates all locked platform requirements before installing packages.
(cd "$root/backend" && composer check-platform-reqs --lock --no-dev --no-plugins --no-interaction)
(cd "$root/backend" && composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-plugins)
(cd "$root/frontend" && npm ci --ignore-scripts && npm run build)
printf '%s\n' 'Install/build completed; no migrations or service activation were performed.' \
  'Package discovery and deployment readiness require separate review; see docs/NATIVE_DEPLOYMENT_PLAN.md.'
