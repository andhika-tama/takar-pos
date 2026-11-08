#!/bin/sh
# One-shot first-run setup. Safe to repeat on every `docker compose up`.
set -e

# .env is a symlink into the volume, and cp refuses to write through a dangling symlink.
[ -f .env ] || cat .env.example > .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

php artisan migrate --force
php artisan db:seed --force
