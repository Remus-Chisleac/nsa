#!/bin/sh
# Render default server from default.conf.in using ONLY these env vars so nginx
# built-ins ($host, $scheme, $admin_ok, …) are not altered by envsubst.
set -e
SRC=/etc/nginx/templates-manual/default.conf.in
if [ ! -f "$SRC" ]; then
  echo "22-render-default: missing $SRC" >&2
  exit 1
fi
envsubst '${APP_DOMAIN} ${PMA_UPSTREAM_HOST} ${PMA_UPSTREAM_PORT}' < "$SRC" > /etc/nginx/conf.d/default.conf
