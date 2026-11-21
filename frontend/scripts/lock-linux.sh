#!/usr/bin/env bash
# Regenerates package-lock.json in a Linux container and checks that `npm ci` accepts it.
# npm on Windows leaves out optional packages that only Linux installs (@emnapi/* for the
# Tailwind and Turbopack WebAssembly fallbacks), so a lockfile written on Windows can fail
# `npm ci` in CI and in the production image. Run after adding or updating a dependency.
set -euo pipefail
cd "$(dirname "$0")/.."
dir="$(pwd -W 2>/dev/null || pwd)"
MSYS_NO_PATHCONV=1 docker run --rm \
  -v "${dir}/package.json:/app/package.json" -v "${dir}/package-lock.json:/app/package-lock.json" -w /app node:22-alpine \
  sh -c "npm install --package-lock-only --ignore-scripts --no-audit --no-fund && npm ci --dry-run --ignore-scripts >/dev/null && echo 'package-lock.json works on Linux.'"
