#!/usr/bin/env bash
# Smoke checks for grading/demo (run after: docker compose up -d && ./scripts/generate-certs.sh).
set -euo pipefail
BASE_HTTP="${BASE_HTTP:-http://localhost:8080}"
BASE_HTTPS="${BASE_HTTPS:-https://localhost:8443}"
echo "== HTTP $BASE_HTTP =="
curl -fsS -o /dev/null "$BASE_HTTP/" && echo "OK index via nginx"
echo "== HTTPS (self-signed: use -k) $BASE_HTTPS =="
curl -fsSk -o /dev/null "$BASE_HTTPS/" && echo "OK index via TLS"
echo "== phpMyAdmin ACL (expect 403 from non-allowed IP if curl uses different egress) =="
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_HTTPS/phpmyadmin/" || true)
echo "HTTP $code (403 expected if IP not in ALLOWED_ADMIN_IPS)"
echo "== Logs dashboard (ACL) =="
code=$(curl -s -o /dev/null -w "%{http_code}" -k "$BASE_HTTPS/logs" || true)
echo "HTTP $code"
echo "== Done =="
