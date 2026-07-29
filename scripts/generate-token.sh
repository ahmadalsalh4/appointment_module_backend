#!/usr/bin/env bash
# Generates a 64-char hex token suitable for INTERNAL_ARTISAN_TOKEN.
# Cross-platform: macOS, Linux, Git Bash on Windows.
set -euo pipefail

if command -v openssl >/dev/null 2>&1; then
  token=$(openssl rand -hex 32)
elif command -v xxd >/dev/null 2>&1; then
  token=$(head -c 32 /dev/urandom | xxd -p -c 32)
else
  token=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
fi

cat <<EOF

Copy this entire string into Render → appointment-module-api → Environment → INTERNAL_ARTISAN_TOKEN:

${token}

Length: ${#token} chars

EOF
