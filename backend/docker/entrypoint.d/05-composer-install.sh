#!/bin/sh
# Development only: install PHP dependencies into the vendor volume on first start,
# and again whenever composer.lock is newer than the installed set.
set -e
cd /var/www/html

if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/composer/installed.json ]; then
  echo "[cybercompass] composer install"
  composer install --no-interaction --prefer-dist --no-progress
fi
