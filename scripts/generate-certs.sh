#!/usr/bin/env bash
# Generate self-signed TLS cert for Nginx on 8443 (replace with Let's Encrypt in production).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CERT_DIR="${ROOT}/certs"
mkdir -p "$CERT_DIR"
openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
  -keyout "$CERT_DIR/server.key" \
  -out "$CERT_DIR/server.crt" \
  -subj "/CN=${APP_DOMAIN:-localhost}"

echo "Wrote $CERT_DIR/server.key and $CERT_DIR/server.crt"
