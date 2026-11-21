#!/usr/bin/env bash
# CI only: the environment files a developer copies by hand (README "First run"), with a fresh
# application key. The values are the local-only defaults from the .env.example files; nothing
# secret exists in CI. Demo accounts stay off (their passwords are empty).
set -euo pipefail

cp .env.example .env
cp backend/.env.example backend/.env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" backend/.env

# The container runs as www-data; the checked-out tree belongs to the runner's user.
chmod -R a+w backend/storage backend/bootstrap/cache
