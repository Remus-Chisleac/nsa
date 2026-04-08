#!/usr/bin/env bash
# Obtain or renew a Let's Encrypt certificate and install it for Nginx.
#
# Usage: ./scripts/setup-letsencrypt.sh <domain> <email>
# Example: ./scripts/setup-letsencrypt.sh soloscout.dev admin@example.com
#
# Requirements:
#   - DNS A record for <domain> already points to this server's public IP.
#   - Run from the project root (where docker-compose.app-node.yml lives).
#   - Docker must be installed.
#   - Port 80 must be reachable from the internet (open in firewall).
#
# How it works:
#   Nginx serves /.well-known/acme-challenge/ from ./certs/acme/ (mounted read-only).
#   Certbot writes the ACME challenge file there, Let's Encrypt fetches it on port 80,
#   then certbot receives the signed certificate. Nginx stays running throughout.
set -euo pipefail

DOMAIN="${1:-}"
EMAIL="${2:-}"

if [[ -z "$DOMAIN" || -z "$EMAIL" ]]; then
  echo "Usage: $0 <domain> <email>"
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CERT_DIR="${ROOT}/certs"
ACME_DIR="${CERT_DIR}/acme"

mkdir -p "${ACME_DIR}" "${CERT_DIR}"

# Detect which compose file is active on this node.
COMPOSE_FILE="${ROOT}/docker-compose.app-node.yml"
[[ ! -f "${COMPOSE_FILE}" ]] && COMPOSE_FILE="${ROOT}/docker-compose.yml"
COMPOSE="docker compose -f ${COMPOSE_FILE} --env-file ${ROOT}/.env"

echo "==> Checking nginx is running (needed to serve the ACME challenge) ..."
if ! ${COMPOSE} ps --services --filter status=running 2>/dev/null | grep -q nginx; then
  echo "  nginx is not running — starting it now with the existing cert ..."
  ${COMPOSE} up -d nginx
  sleep 3
fi

echo "==> Requesting certificate for ${DOMAIN} via webroot (nginx stays up) ..."
docker run --rm \
  -v "${ACME_DIR}:/var/www/certbot" \
  -v /etc/letsencrypt:/etc/letsencrypt \
  -v /var/lib/letsencrypt:/var/lib/letsencrypt \
  certbot/certbot certonly \
    --webroot \
    -w /var/www/certbot \
    --non-interactive \
    --agree-tos \
    --email "${EMAIL}" \
    -d "${DOMAIN}"

echo "==> Installing certificate into ${CERT_DIR} ..."
cp /etc/letsencrypt/live/"${DOMAIN}"/fullchain.pem "${CERT_DIR}/server.crt"
cp /etc/letsencrypt/live/"${DOMAIN}"/privkey.pem   "${CERT_DIR}/server.key"
chmod 644 "${CERT_DIR}/server.crt"
chmod 600 "${CERT_DIR}/server.key"

echo "==> Reloading nginx ..."
${COMPOSE} exec nginx nginx -s reload

echo ""
echo "==> Installing monthly renewal cron job ..."
cat | sudo tee /etc/cron.d/letsencrypt-renew > /dev/null << CRON
# Renew Let's Encrypt cert for ${DOMAIN} on the 1st of each month at 03:00
0 3 1 * * root \
  docker run --rm \
    -v "${ACME_DIR}:/var/www/certbot" \
    -v /etc/letsencrypt:/etc/letsencrypt \
    -v /var/lib/letsencrypt:/var/lib/letsencrypt \
    certbot/certbot renew --webroot -w /var/www/certbot --quiet && \
  cp /etc/letsencrypt/live/${DOMAIN}/fullchain.pem ${CERT_DIR}/server.crt && \
  cp /etc/letsencrypt/live/${DOMAIN}/privkey.pem ${CERT_DIR}/server.key && \
  chmod 644 ${CERT_DIR}/server.crt && \
  chmod 600 ${CERT_DIR}/server.key && \
  docker compose -f ${COMPOSE_FILE} --env-file ${ROOT}/.env exec nginx nginx -s reload
CRON

echo ""
echo "Done. https://${DOMAIN}/ now has a trusted Let's Encrypt certificate."
echo "Auto-renewal cron is installed at /etc/cron.d/letsencrypt-renew"
