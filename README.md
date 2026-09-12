# Document Scanner (Laravel + ScanServJS)

A self-hosted document scanning web app that turns any network AirScan/eSCL MFP into a one-click browser scanning station. Built with **Laravel 13** + **[ScanServJS](https://github.com/sbs20/scanervjs)** (Docker).

Open `/scan`, click **Scan Single Page** or **Scan ADF**, preview, then **Save Document** (JPG or multi-page PDF) to Laravel storage and the `scanned_documents` table.

***

## Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [SCAN SERVE JS Folder](#scan-serve-js-folder)
- [Laravel Configuration](#laravel-configuration)
- [Production Deployment](#production-deployment)
- [Troubleshooting](#troubleshooting)
- [Security Checklist](#security-checklist)
- [License](#license)

***

## Architecture

```
Browser  →  Laravel (HTTPS)  →  ScanServJS API :8080  →  SANE / eSCL  →  MFP
```

Laravel never talks to the scanner directly. It calls ScanServJS's REST API, which uses SANE over eSCL/AirScan to drive the MFP.

***

## Prerequisites

- PHP 8.3+
- Composer 2.x
- Node.js 20+ & npm
- Docker Desktop (for ScanServJS)
- Any eSCL/AirScan-compatible MFP on the same network as the server

***

## Quick Start

```bash
# 1. ScanServJS (Docker)
cd "SCAN SERVE JS"
docker compose up -d
# open http://localhost:8080 — confirm your MFP shows up

# 2. Laravel
cd ..
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

Open `http://127.0.0.1:8000/scan`, scan, save.

***

## SCAN SERVE JS Folder

Standalone Docker project that runs the [sbs20/scanservjs](https://github.com/sbs20/scanervjs) image, decoupled from the Laravel app so it can run on a different host if needed.

### Key files

- **`docker-compose.yml`** — service definition, port `8080`, `TZ`, `SANE_AIRSCAN_DEVICE`, volume mount for `./data`
- **`airscan.conf`** — pins the SANE airscan backend to a specific MFP
- **`data/`** — persistent volume for scanned files
- **`scanservjs.js`** — vendored ScanServJS bundle

### Configure for your own printer

Both `docker-compose.yml` and `airscan.conf` reference the MFP by IP. **Edit both files** and replace the IP with yours:

```yaml
# docker-compose.yml
environment:
  SANE_AIRSCAN_DEVICE: "escl:<Your Printer Name>:http://<YOUR_PRINTER_IP>/eSCL"
```

```ini
# airscan.conf
[devices]
"<Your Printer Name>" = "http://<YOUR_PRINTER_IP>/eSCL, eSCL"

[options]
discovery = disable
```

> Give your MFP a **static IP or DHCP reservation** — if the IP changes, scanning silently breaks.

### Production hardening

```yaml
services:
  scanservjs:
    image: sbs20/scanservjs:latest
    container_name: scanservjs
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:8080"   # bind to localhost; reverse-proxy for remote Laravel
    environment:
      TZ: Asia/Kabul
      SANE_AIRSCAN_DEVICE: "escl:HP M281fdw:http://10.10.27.63/eSCL"
    volumes:
      - ./data:/var/lib/scanservjs/output
      - ./airscan.conf:/etc/sane.d/airscan.conf:ro
    read_only: true
    tmpfs: ["/tmp", "/run"]
    security_opt: ["no-new-privileges:true"]
    cap_drop: ["ALL"]
    cap_add: ["NET_BIND_SERVICE"]
```

- Do not expose `8080` publicly; front it with Nginx/Caddy + TLS.
- Pin the image tag (`v3.x.y`) once validated.

### Helper scripts (Windows)

`SCAN SERVE JS/START.bat`:

```bat
@echo off
cd /d "%~dp0"
docker compose up -d
echo Scanservjs is running at http://localhost:8080
pause
```

`SCAN SERVE JS/STOP.bat`:

```bat
@echo off
cd /d "%~dp0"
docker compose down
pause
```

***

## Laravel Configuration

### `.env` (production values)

```env
APP_NAME="Document Scanner"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://scanner.example.com
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=scanner
DB_USERNAME=scanner
DB_PASSWORD=<from-secret-manager>

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
FILESYSTEM_DISK=public

SCANSERVJS_URL=http://127.0.0.1:8080
SCANSERVJS_TIMEOUT=600
```

SQLite is used by default for local dev.

### Key environment variables

| Variable                                              | Notes                                                                |
| ----------------------------------------------------- | -------------------------------------------------------------------- |
| `APP_ENV` / `APP_DEBUG`                               | **production / false** in live                                       |
| `APP_KEY`                                             | Generated, **never commit**                                          |
| `DB_CONNECTION`                                       | `mysql` or `pgsql` for production                                    |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | Use `redis` in production                                            |
| `SCANSERVJS_URL`                                      | URL Laravel uses to reach ScanServJS — change if on a different host |
| `SCANSERVJS_TIMEOUT`                                  | HTTP timeout; controller already raises this for scans               |

### Storage

```bash
php artisan storage:link   # run on every deploy
```

Layout:

```
storage/app/public/
├── scanner-temp/   # transient — deleted after Save
└── documents/      # permanent — recorded in `scanned_documents`
```

### API endpoints

| Method | Route         | Purpose                                    |
| ------ | ------------- | ------------------------------------------ |
| `GET`  | `/scan`       | Scanner UI                                 |
| `POST` | `/scan/start` | Single-page flatbed scan → JPG             |
| `POST` | `/scan/adf`   | ADF multi-page scan → single PDF           |
| `POST` | `/scan/save`  | Persist the temp file to `documents/` + DB |

`/scan/save` validates that `temp_path` starts with `scanner-temp/` to block path traversal.

***

## Production Deployment

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name scanner.example.com;
    root /var/www/scanner/public;

    ssl_certificate     /etc/letsencrypt/live/scanner.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/scanner.example.com/privkey.pem;

    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    client_max_body_size 100M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 600;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

### systemd

`/etc/systemd/system/scanner.service`:

```ini
[Unit]
Description=Laravel Document Scanner
After=network.target docker.service
Requires=docker.service

[Service]
User=www-data
WorkingDirectory=/var/www/scanner
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=1
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now scanner
```

### On every deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache route:cache view:cache event:cache
php artisan storage:link
sudo systemctl restart scanner
cd "SCAN SERVE JS" && docker compose pull && docker compose up -d
```

### Backups

Back up: **DB**, `storage/app/public/documents/`, `.env`, and the `SCAN SERVE JS/data/` volume.

***

## Troubleshooting

| Symptom                             | Cause                               | Fix                                                                |
| ----------------------------------- | ----------------------------------- | ------------------------------------------------------------------ |
| `Connection refused` on `:8080`     | ScanServJS not running              | `docker compose up -d` from `SCAN SERVE JS/`                       |
| `device not found` (`scanimage -L`) | MFP IP changed / AirScan off        | Reserve static IP; `curl http://<ip>/eSCL/ScannerCapabilities`     |
| ADF returns a JPG instead of PDF    | Wrong `source` / `pipeline`         | Use `source: ADF`, `pipeline: PDF (JPG \| @:pipeline.low-quality)` |
| `Vite manifest` error               | Assets not built                    | `npm run build`                                                    |
| Storage permission errors           | Wrong owner                         | `sudo chown -R www-data:www-data storage bootstrap/cache`          |
| Mixed-content warnings              | `APP_URL` is `http://` behind HTTPS | Set `APP_URL=https://…` and `php artisan config:clear`             |
| CSRF token mismatch                 | Bad session driver                  | Use `database` or `redis` for `SESSION_DRIVER`                     |

***

## Security Checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` set, `.env` in `.gitignore`
- [ ] HTTPS via Let's Encrypt; HSTS on
- [ ] ScanServJS port `8080` bound to `127.0.0.1` or firewalled
- [ ] DB credentials from a secret manager
- [ ] `storage/` and `bootstrap/cache/` owned by the web user
- [ ] Automated, tested backups

