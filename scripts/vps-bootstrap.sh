#!/usr/bin/env bash
# Run on Ubuntu 24.04 as root or with sudo. Sets hostname and baseline packages.
# Usage: sudo ./vps-bootstrap.sh app-node   OR   sudo ./vps-bootstrap.sh data-node
set -euo pipefail

ROLE="${1:-}"
if [[ "$ROLE" != "app-node" && "$ROLE" != "data-node" ]]; then
  echo "Usage: $0 {app-node|data-node}"
  exit 1
fi

hostnamectl set-hostname "$ROLE"
echo "127.0.1.1 $ROLE" >> /etc/hosts

export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y
apt-get install -y ca-certificates curl gnupg ufw fail2ban

echo "Hostname set to $ROLE. Configure SSH keys and firewall next (see scripts/firewall-ufw.sh)."
