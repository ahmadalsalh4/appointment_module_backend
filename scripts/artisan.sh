#!/usr/bin/env bash
# Wrapper for the Render artisan bridge.
#
# Usage:
#   INTERNAL_ARTISAN_TOKEN=<token> ./artisan.sh status
#   INTERNAL_ARTISAN_TOKEN=<token> ./artisan.sh migrate
#   INTERNAL_ARTISAN_TOKEN=<token> ./artisan.sh list
#
# Or set the env var in your shell first:
#   export INTERNAL_ARTISAN_TOKEN="..."
#   ./artisan.sh status

set -euo pipefail

BASE_URL="${BASE_URL:-https://appointment-module-api.onrender.com}"
TOKEN="${INTERNAL_ARTISAN_TOKEN:-}"

command="${1:-status}"
shift || true
flags=()
args=()

while [ "$#" -gt 0 ]; do
  case "$1" in
    --Flags) shift; IFS=',' read -r -a flags <<< "$1" ;;
    --flags) shift; IFS=',' read -r -a flags <<< "$1" ;;
    --Args)  shift; IFS=',' read -r -a args  <<< "$1" ;;
    --args)  shift; IFS=',' read -r -a args  <<< "$1" ;;
    *) echo "Unknown flag: $1" >&2; exit 2 ;;
  esac
  shift || break
done

if [ -z "$TOKEN" ]; then
  cat >&2 <<EOF
INTERNAL_ARTISAN_TOKEN env var is empty.

Quickest path: open Render Dashboard → appointment-module-api → Environment,
add the INTERNAL_ARTISAN_TOKEN env var with a random 32+ char string, then
from this shell:

    export INTERNAL_ARTISAN_TOKEN="paste-the-token"

Run ./generate-token.sh to generate a strong value automatically.
EOF
  exit 2
fi

# Friendly alias → bridge public-key + per-command default flags.
declare -A map
map["status"]="app-status:"
map["app-status"]="app-status:"
map["migrate"]="migrate:--force"
map["migrate-status"]="migrate-status:"
map["migrate-fresh"]="migrate-fresh:--force"
map["migrate-rollback"]="migrate-rollback:--force"
map["config-clear"]="config-clear:"
map["cache-clear"]="cache-clear:"
map["route-clear"]="route-clear:"
map["view-clear"]="view-clear:"
map["event-clear"]="event-clear:"
map["optimize"]="optimize:"
map["optimize-clear"]="optimize-clear:"
map["storage-link"]="storage-link:"
map["queue-work-once"]="queue-work-once:"
map["dedupe-preview"]="dedupe-preview:--dry-run"
map["list"]="list:"

if [ "$command" = "list" ]; then
  printf '%s\n' "Allowed commands:"
  printf '  %s\n' "${!map[@]}" | sort
  exit 0
fi

if [ -z "${map[$command]+x}" ]; then
  echo "Unknown command '$command'. Run './artisan.sh list' to see the allowlist." >&2
  exit 2
fi

spec="${map[$command]}"
public_key="${spec%%:*}"
default_flags="${spec#*:}"

if [ "${#flags[@]}" -eq 0 ] && [ -n "$default_flags" ]; then
  IFS=',' read -r -a flags <<< "$default_flags"
fi

# Build JSON body without depending on jq.
body_json=$(printf '{"command":"%s"' "$public_key")
if [ "${#flags[@]}" -gt 0 ]; then
  flag_csv=$(printf '"%s",' "${flags[@]}")
  body_json="$body_json,\"flags\":[${flag_csv%,}]"
fi
if [ "${#args[@]}" -gt 0 ]; then
  arg_csv=$(printf '"%s",' "${args[@]}")
  body_json="$body_json,\"args\":[${arg_csv%,}]"
fi
body_json="$body_json}"

printf '→ POST %s/api/internal/artisan cmd=%s flags=[%s] args=[%s]\n' \
  "$BASE_URL" "$public_key" "${flags[*]:-}" "${args[*]:-}" >&2

http_status=$(curl -s -o /tmp/artisan_response.json -w "%{http_code}" \
  -X POST "$BASE_URL/api/internal/artisan" \
  -H "X-Internal-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$body_json" \
  --max-time 120) || {
  echo "curl failed" >&2
  exit 3
}

echo "HTTP $http_status"
echo

# Pretty-print if jq is available, otherwise raw.
if command -v jq >/dev/null 2>&1; then
  jq . </tmp/artisan_response.json
else
  cat /tmp/artisan_response.json
  echo
fi

if [ "$http_status" -ge 400 ]; then
  exit 4
fi
