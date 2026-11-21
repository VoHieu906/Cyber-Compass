#!/usr/bin/env bash
# CI only: start the given compose services and wait until the API answers its health check.
# The api container installs Composer dependencies on its first start (backend/docker/entrypoint.d).
set -euo pipefail

docker compose up -d --wait mysql
docker compose up -d "$@"

for i in $(seq 1 90); do
  if curl -fsS http://localhost:8000/api/v1/health >/dev/null 2>&1; then
    echo "API is up."
    exit 0
  fi
  sleep 10
done

echo "The API did not become healthy." >&2
docker compose logs api --tail=200 >&2
exit 1
