#!/bin/sh
set -e
GEO_FILE=/etc/nginx/conf.d/00-geo.conf
{
  echo "geo \$admin_ok {"
  echo "  default 0;"
  OLDIFS=$IFS
  IFS=,
  for ip in $ALLOWED_ADMIN_IPS; do
    ip=$(echo "$ip" | tr -d '[:space:]')
    if [ -n "$ip" ]; then
      echo "  $ip 1;"
    fi
  done
  IFS=$OLDIFS
  echo "}"
} > "$GEO_FILE"
