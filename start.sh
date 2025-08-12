#!/usr/bin/env bash
set -e
PORT="${PORT:-10000}"
exec php -S "0.0.0.0:${PORT}" -t .
