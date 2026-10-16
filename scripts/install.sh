#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
command -v php >/dev/null || { echo 'PHP 8.3+ diperlukan.'; exit 1; }
command -v composer >/dev/null || { echo 'Composer 2 diperlukan.'; exit 1; }
composer install --prefer-dist --no-interaction
php scripts/setup.php "$@"
echo 'Aset siap pakai tersedia. Untuk rebuild: npm ci && npm run build.'
