#!/bin/sh
set -e
# The stock nginx image symlinks access.log/error.log to stdout/stderr. That breaks
# `wc`, GoAccess, and any tool that expects real files on the shared nginx-logs volume.
LOGDIR=/var/log/nginx
mkdir -p "$LOGDIR"
for name in access.log error.log; do
  f="$LOGDIR/$name"
  if [ -L "$f" ] || [ ! -e "$f" ]; then
    rm -f "$f"
    : >"$f"
  fi
  chown nginx:nginx "$f" 2>/dev/null || chown 101:101 "$f" 2>/dev/null || true
  chmod 644 "$f"
done
