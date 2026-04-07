#!/usr/bin/env bash
# UFW rules for app-node (public web) or data-node (DB + admin).
# Edit APP_NODE_IP / DATA_NODE_IP before use on split-VPS deployment.
set -euo pipefail

ROLE="${1:-}"
if [[ "$ROLE" != "app-node" && "$ROLE" != "data-node" ]]; then
  echo "Usage: $0 {app-node|data-node}"
  echo "Optional: export APP_NODE_IP=x.x.x.x DATA_NODE_IP=y.y.y.y for cross-node rules"
  exit 1
fi

ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH

if [[ "$ROLE" == "app-node" ]]; then
  ufw allow 8080/tcp comment 'nginx http'
  ufw allow 8443/tcp comment 'nginx https'
elif [[ "$ROLE" == "data-node" ]]; then
  # MariaDB + phpMyAdmin mail UI: restrict to app node when set
  if [[ -n "${APP_NODE_IP:-}" ]]; then
    ufw allow from "${APP_NODE_IP}" to any port 3306 proto tcp comment 'mariadb from app'
    ufw allow from "${APP_NODE_IP}" to any port 8081 proto tcp comment 'phpmyadmin proxy from app'
  else
    echo "WARN: APP_NODE_IP not set; not opening 3306/8081 (set for split deployment)."
  fi
  ufw allow 8025/tcp comment 'mailpit web (optional; restrict in production)'
fi

ufw --force enable
ufw status verbose
