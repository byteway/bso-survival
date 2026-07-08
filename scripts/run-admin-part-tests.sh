#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
php vendor/bin/phpunit --filter 'EventAdminServiceTest|PartAdminServiceTest' tests/Service
