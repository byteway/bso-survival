#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${PROJECT_ROOT}/.dev/wp-dev.env"
F603_SCRIPT="${PROJECT_ROOT}/tests/manual/f6-03-smoke.sh"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: ontbrekend secrets-bestand: $ENV_FILE" >&2
    echo "Kopieer ${PROJECT_ROOT}/.dev/wp-dev.env.example naar .dev/wp-dev.env en vul je echte waarden in." >&2
    exit 1
fi

if [[ ! -x "$F603_SCRIPT" ]]; then
    chmod +x "$F603_SCRIPT"
fi

# shellcheck disable=SC1090
set -a
source "$ENV_FILE"
set +a

: "${WORDPRESS_DB_HOST:?WORDPRESS_DB_HOST is verplicht}"
: "${WORDPRESS_DB_NAME:?WORDPRESS_DB_NAME is verplicht}"
: "${WORDPRESS_DB_USER:?WORDPRESS_DB_USER is verplicht}"
: "${WORDPRESS_DB_PASSWORD:?WORDPRESS_DB_PASSWORD is verplicht}"
: "${WORDPRESS_PATH:?WORDPRESS_PATH is verplicht}"

EVENT_ID="${1:-${BSO_SMOKE_EVENT_ID:-2}}"

export PAGER=cat

# Ensure DB schema is aligned before running visibility-window checks.
wp --path="$WORDPRESS_PATH" eval "if (class_exists('BSO\\Survival\\Database\\Migrator')) { BSO\\Survival\\Database\\Migrator::migrate(); echo 'Schema migration: OK'.PHP_EOL; }"

exec "$F603_SCRIPT" "$EVENT_ID"
