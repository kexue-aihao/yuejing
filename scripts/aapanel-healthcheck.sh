#!/usr/bin/env bash

set -Eeuo pipefail

URL="${HEALTHCHECK_URL:-http://127.0.0.1/up}"
TIMEOUT="${HEALTHCHECK_TIMEOUT:-10}"

if ! command -v curl >/dev/null 2>&1; then
    printf '%s healthcheck ERROR: curl not found\n' "$(date -Is)" >&2
    exit 127
fi

if ! [[ "$TIMEOUT" =~ ^[0-9]+$ ]] || [ "$TIMEOUT" -lt 1 ]; then
    printf '%s healthcheck ERROR: HEALTHCHECK_TIMEOUT must be a positive integer\n' "$(date -Is)" >&2
    exit 2
fi

http_code="$(curl --silent --show-error --location --max-time "$TIMEOUT" \
    --output /dev/null --write-out '%{http_code}' "$URL")" || {
    printf '%s healthcheck FAIL: request failed url=%s\n' "$(date -Is)" "$URL" >&2
    exit 1
}

if [[ "$http_code" != 2* ]]; then
    printf '%s healthcheck FAIL: status=%s url=%s\n' "$(date -Is)" "$http_code" >&2
    exit 1
fi

printf '%s healthcheck OK: status=%s url=%s\n' "$(date -Is)" "$http_code"
