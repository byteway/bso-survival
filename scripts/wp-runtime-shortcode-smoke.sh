#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${PROJECT_ROOT}/.dev/wp-dev.env"
SMOKE_FILE="${PROJECT_ROOT}/tests/manual/runtime-shortcode-smoke.php"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: ontbrekend secrets-bestand: $ENV_FILE" >&2
    echo "Kopieer ${PROJECT_ROOT}/.dev/wp-dev.env.example naar .dev/wp-dev.env en vul je echte waarden in." >&2
    exit 1
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

if [[ ! -f "$SMOKE_FILE" ]]; then
    echo "ERROR: smoke file niet gevonden: $SMOKE_FILE" >&2
    exit 1
fi

export PAGER=cat
wp --path="$WORDPRESS_PATH" core is-installed >/dev/null
wp --path="$WORDPRESS_PATH" eval-file "$SMOKE_FILE"
