# Networking class project — deployment and demo

This repository runs a Docker Compose stack: Nginx (HTTP **8080**, HTTPS **8443**), two PHP web replicas, Redis (sessions), MariaDB primary + replica, phpMyAdmin, Mailpit, and GoAccess-style access log HTML at **`/logs`**.

**Database routing (PHP):** The app probes **`DB_HOST`** (primary) and **`DB_REPLICA_HOST`** (replica). If both are reachable, **writes** always go to the primary; **reads** are **load-balanced** with round-robin between primary and replica. If only the primary is up, all queries use the primary. If the **primary is down** but the replica is up, **reads** use the replica (MariaDB **`read_only=1`**); **writes** fail until the primary is back. The UI shows **which host served the last read** and, when the primary is down, **last primary check time from Redis** plus **escalating background re-probes** (5s → 10s → 30s → 60s) and a **manual “Check primary now”** button. If `SHOW REPLICA STATUS` / `Slave_SQL_Running` is **No**, try `docker compose restart db-replica` or, as a last resort, remove the replica volume and let `replica-setup` rebuild the slave from the primary.

You can run everything on **one machine** for development, or split across **two VPSs** (application node vs data node) as required by the project plan.

---

## What runs where (two-VPS layout)

| Server (role) | Compose file | Services | Published ports (typical) |
|---------------|--------------|----------|---------------------------|
| **VPS1 — app node** | `docker-compose.app-node.yml` | nginx, web1, web2, redis, goaccess | **8080**, **8443** |
| **VPS2 — data node** | `docker-compose.data-node.yml` | db-primary, db-replica, replica-setup, phpmyadmin, mailpit | **3306**, **8081** (phpMyAdmin), **1025** (SMTP), **8025** (Mailpit UI) |

On **VPS1**, the app must reach **VPS2** for the database, SMTP, and the Nginx proxy target for phpMyAdmin. Use the **public IP** of VPS2 in `.env` (see below).

**Firewall (recommended):**

- **VPS2:** Allow SSH from your IP. Allow **3306**, **8081**, **1025** (and optionally **8025**) **only from VPS1’s public IP** (or your office IP for demos).
- **VPS1:** Allow **8080** and **8443** (and SSH) from the internet or your demo audience as appropriate.

---

## One-time preparation (each server)

1. **Clone or copy this project** to the server (same paths on both if you use split compose), e.g. `/opt/networking-project`.
2. **Install Docker Engine and Compose plugin** (see `scripts/install-docker.sh` or [Docker’s Ubuntu docs](https://docs.docker.com/engine/install/ubuntu/)).
3. **Copy environment file:** `cp .env.example .env` and edit passwords and hostnames (see sections below).
4. **TLS for Nginx (VPS1 or single host):** `./scripts/generate-certs.sh`  
   For production, replace with Let’s Encrypt or certificates your instructor allows.
5. **Optional — OS hardening:** `scripts/vps-bootstrap.sh` and `scripts/firewall-ufw.sh` (adjust IPs for your layout).

---

## Configuration: `.env`

- Copy from **`.env.example`** and set strong passwords.
- **Single-host stack** (`docker-compose.yml`): defaults use Docker service names (`DB_HOST=db-primary`, `SMTP_HOST=mailpit`, `PMA_UPSTREAM_HOST=phpmyadmin`).
- **Split stack:**
  - **VPS2 (`data-node`):** Use the same `DB_*`, `MYSQL_*`, `REPL_*` as VPS1. Set `PMA_ABSOLUTE_URI` to your public URL, e.g. `https://app.example.com:8443/phpmyadmin/`.
  - **VPS1 (`app-node`):** Point everything at VPS2, for example:
    - `DB_HOST=<VPS2_PUBLIC_IP>`
    - `SMTP_HOST=<VPS2_PUBLIC_IP>`
    - `SMTP_PORT=1025`
    - `PMA_UPSTREAM_HOST=<VPS2_PUBLIC_IP>`
    - `PMA_UPSTREAM_PORT=8081`
    - `APP_BASE_URL` and `APP_DOMAIN` to your real domain (DNS **A** record → VPS1).
  - **`ALLOWED_ADMIN_IPS`:** Comma-separated IPs allowed to open **`/phpmyadmin/`** and **`/logs`** through Nginx (your home/office IP for grading).

---

## Run on each server

### Option A — Everything on one host (simplest for testing)

From the project root:

```bash
./scripts/generate-certs.sh
docker compose --env-file .env up -d --build
```

- App: `http://localhost:8080` and `https://localhost:8443` (browser will warn on self-signed TLS).
- **Mailpit UI (unified `docker-compose.yml`):** `http://localhost:8025` (ports **8025** / **1025** are published).

Check status:

```bash
docker compose ps
docker compose logs -f nginx
```

### Option B — VPS2 first (data node)

```bash
cd /path/to/project
cp .env.example .env   # edit MYSQL_*, DB_*, REPL_*, PMA_ABSOLUTE_URI
docker compose -f docker-compose.data-node.yml --env-file .env up -d
```

Confirm MariaDB and replication (after `replica-setup` exits):

```bash
docker compose -f docker-compose.data-node.yml ps
docker compose -f docker-compose.data-node.yml logs replica-setup
```

Mailpit web UI (if firewall allows): `http://<VPS2_IP>:8025`.

### Option C — VPS1 (app node), after VPS2 is up

On VPS1, `.env` must use VPS2’s IP for DB, SMTP, and PMA upstream (see above). Generate certs on **VPS1**:

```bash
./scripts/generate-certs.sh
docker compose -f docker-compose.app-node.yml --env-file .env up -d --build
```

Public entry: `http://<VPS1_IP>:8080`, `https://<VPS1_IP>:8443` (or your domain on port 8080/8443).

---

## Useful Docker commands

### Images and containers

```bash
# All containers (running and stopped)
docker ps -a

# Only running containers
docker ps

# All images
docker images

# Images used by current compose project
docker compose images

# Service status for this repo
docker compose ps
# Split files:
docker compose -f docker-compose.data-node.yml ps
docker compose -f docker-compose.app-node.yml ps
```

### Logs and debugging

```bash
# Follow logs for all services
docker compose logs -f

# One service
docker compose logs -f nginx
docker compose logs -f web1

# Last 100 lines
docker compose logs --tail=100 nginx
```

### Start / stop / rebuild

```bash
docker compose stop
docker compose start
docker compose down              # stop and remove containers (volumes kept)
docker compose down -v           # also remove named volumes (wipes DBs)
docker compose up -d --build     # rebuild images and start
```

### Execute commands inside a container

```bash
docker compose exec web1 php -v
docker compose exec db-primary mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SHOW SLAVE STATUS\G"
```

(Use the correct compose file `-f` when using split stacks.)

### Disk usage (images, volumes)

```bash
docker system df
docker volume ls
```

---

## How to present / demo the project

1. **Architecture (30–60 seconds):** One entry point (Nginx on 8080/8443), two web replicas behind load balancing, Redis for shared sessions, MariaDB primary + replica on the data side, Mailpit for verification email, GoAccess HTML at **`/logs`** (protected by the same IP ACL as phpMyAdmin).
2. **Open the app:** `https://<your-domain-or-VPS1>:8443` (accept certificate warning if self-signed).
3. **Show replica identity:** Reload the home page several times; **`Served by`** should alternate between **web1** and **web2** (or show different hostnames).
4. **Session persistence:** Log in, refresh many times; you should stay logged in on both replicas (Redis sessions).
5. **Registration + email:** Register a user; open Mailpit at `http://localhost:8025` (unified compose) or `http://<VPS2>:8025` (split setup); click the verification link; then log in.
6. **CRUD:** Create/edit/delete items while logged in.
7. **phpMyAdmin:** Open `https://<host>:8443/phpmyadmin/` from an **allowed** IP; use the **Server** dropdown (**Primary** / **Replica**). Servers are defined in [`phpmyadmin/config.user.inc.php`](phpmyadmin/config.user.inc.php): the replica uses a **control connection to the primary** so phpMyAdmin does not try to write metadata on the read-only replica. **`PMA_ABSOLUTE_URI`** must use the **same hostname** you use in the browser (`localhost` vs `127.0.0.1` are different sites for cookies).
8. **ACL:** From a **non-allowed** IP (or VPN), show **403** on `/phpmyadmin/` and `/logs`.
9. **Logs dashboard:** From an allowed IP, open `https://<host>:8443/logs` and explain aggregated access statistics.
10. **Failure checks (optional):** `docker compose stop web1` — site still works; `docker compose stop web2` — same; restore with `docker compose start web1 web2`.

**Quick smoke test script (single host / after TLS):**

```bash
./scripts/final-verification.sh
```

---

## Ports reference

| Port | Service |
|------|---------|
| 8080 | Nginx HTTP |
| 8443 | Nginx HTTPS |
| 3306 | MariaDB (restrict in firewall) |
| 6379 | Redis (internal to app stack) |
| 8081 | phpMyAdmin on data node (when using split compose) |
| 1025 | Mailpit SMTP |
| 8025 | Mailpit web UI |

---

## Troubleshooting

- **Nginx `invalid variable name` / won’t start:** The image’s template `envsubst` must not replace nginx’s own `$host`, `$scheme`, etc. This repo renders [`nginx/conf.d/default.conf.in`](nginx/conf.d/default.conf.in) with [`nginx/docker-entrypoint.d/22-render-default.sh`](nginx/docker-entrypoint.d/22-render-default.sh) using **only** `${APP_DOMAIN}`, `${PMA_UPSTREAM_HOST}`, and `${PMA_UPSTREAM_PORT}`. Ensure **`15-geo.sh`** and **`22-render-default.sh`** are executable on the host (`chmod +x`). If nginx keeps crashing, test config without a long-running container: `docker compose run --rm --no-deps nginx nginx -t`.
- **502 / bad gateway:** Check `docker compose ps`; ensure `web1`/`web2` are up; on split deploy, verify VPS1 can reach VPS2: `nc -zv <VPS2_IP> 3306` (and 1025, 8081).
- **Session lost across refreshes:** Confirm Redis is running and `REDIS_HOST`/`REDIS_PORT` in app containers point to the Redis service on the app node.
- **Replication:** Inspect `replica-setup` logs; on replica, `SHOW SLAVE STATUS\G` should show `Slave_IO_Running` and `Slave_SQL_Running` as `Yes`.
- **403 on phpMyAdmin or /logs:** Your client IP must be listed in `ALLOWED_ADMIN_IPS` (comma-separated). Behind NAT, use your **public** egress IP. Docker often appears as **`172.17.0.1`** (or similar), not `127.0.0.1`, even when you browse to `localhost`.
- **Blank `/logs`:** Nginx must write a **real** `access.log` on the shared `nginx-logs` volume (see [`nginx/docker-entrypoint.d/zz-real-access-log.sh`](nginx/docker-entrypoint.d/zz-real-access-log.sh)). Recreate **`nginx`** (and **`goaccess`**) after changes, generate some traffic, wait ~15s, then reload `/logs`. If `access.log` was still a symlink to `/dev/stdout`, `wc -l` on that path can appear to hang — use `docker compose exec -T nginx stat /var/log/nginx/access.log` to confirm it is a regular file.
- **phpMyAdmin “breaks” on Replica:** The replica runs with **`read_only=1`**. The config points **control** (`controlhost`) at **`db-primary`** so UI metadata is not written on the replica. Align **`PMA_ABSOLUTE_URI`** in `.env` with the URL host you actually use. Recreate **`phpmyadmin`** and **`nginx`** after changing [`phpmyadmin/config.user.inc.php`](phpmyadmin/config.user.inc.php) or Nginx timeouts.
- **`Invalid address: (From): …` / mail fails on registration:** Set **`SMTP_FROM`** to a syntactically valid address PHPMailer accepts (e.g. `noreply@example.com`). Avoid `noreply@localhost` — it is rejected as invalid. Registration sends mail; login does not.
- **Password reset:** New installs include `reset_token` / `reset_expires_at` on `users`. Existing DB volumes created before that need: [`scripts/add-password-reset-columns.sql`](scripts/add-password-reset-columns.sql) (run via `docker compose exec db-primary mariadb …` as in the file comment).
- **`No database is reachable` / both primary and replica failed:** The login page shows **details** from MariaDB/PDO (after a code update). Typical causes: `db-primary` / `db-replica` containers not running (`docker compose ps`, then `docker compose up -d`), wrong **`DB_HOST` / `DB_REPLICA_HOST`** for your setup, wrong **`DB_PASSWORD`**, or app containers not on the same Compose project/network as the DBs. Ensure **`DB_NAME`** matches (`appdb`) and MariaDB finished starting (wait ~30s after `up`).

For OS-level setup (hostname, UFW), see `scripts/vps-bootstrap.sh` and `scripts/firewall-ufw.sh`.
