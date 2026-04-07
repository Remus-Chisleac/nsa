#!/usr/bin/env bash
# One-shot replication bootstrap (used by replica-setup service).
set -euo pipefail
for _ in $(seq 1 90); do
  mariadb -h db-primary -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1" 2>/dev/null && break
  sleep 2
done
for _ in $(seq 1 90); do
  mariadb -h db-replica -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1" 2>/dev/null && break
  sleep 2
done
mariadb -h db-replica -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "STOP SLAVE;" 2>/dev/null || true
mariadb -h db-replica -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "RESET SLAVE ALL;" 2>/dev/null || true
mariadb -h db-replica -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "CHANGE MASTER TO MASTER_HOST='db-primary', MASTER_USER='repl', MASTER_PASSWORD='${REPL_PASSWORD}', MASTER_USE_GTID=slave_pos;"
mariadb -h db-replica -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "START SLAVE;"
echo "Replication configured."
