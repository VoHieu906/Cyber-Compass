#!/usr/bin/env bash
# CI only: start the given compose services, migrate (and with --seed, seed the content), then
# wait until the API answers its health check. The api container installs Composer dependencies
# on its first start (backend/docker/entrypoint.d), so artisan becomes usable after a while.
# Migrations come before the health check: the API's rate limiter keeps its counters in the
# database cache table, so an unmigrated API answers 500.
#   start-stack.sh [--seed] service...
set -euo pipefail

seed=""
if [ "${1:-}" = "--seed" ]; then
  seed="--seed"
  shift
fi

docker compose up -d --wait mysql
docker compose up -d "$@"

wait_for() {
  local what="$1"
  shift
  for _ in $(seq 1 60); do
    if "$@" >/dev/null 2>&1; then
      echo "$what: ready."
      return 0
    fi
    sleep 5
  done
  echo "$what: not ready after 5 minutes." >&2
  docker compose logs api --tail=100 >&2
  return 1
}

wait_for "Composer dependencies" docker compose exec -T api php artisan --version
docker compose exec -T api php artisan migrate --force $seed
wait_for "API health" curl -fsS http://localhost:8000/api/v1/health
