# SQL PowerTools ⚡️ 

Safely clone, export, and back up MySQL databases — with security-first features like sensitive data masking, session-backed credentials, and secure mysqldump execution.

## ✨ Features

- 🧪 Clone structure and sample X rows per table
- 🎯 Deterministic sampling (ORDER BY primary key)
- 💾 Full backups (schema + data), optional gzip
- 🔒 Sensitive column auto-detection + per-table overrides
- 🛡️ Secure mysqldump via defaults file (no creds in args)
- 🔐 Session-backed DB creds, CSRF, and security headers
- 🌙 Polished dark/light UI

## 📦 Requirements

- PHP 8.0+
- MySQL 5.7+/8.x with access to `information_schema`
- `mysqldump` available in PATH (auto-discovered on WAMP)

## 🚀 Quick Start

1. Clone and install

```bash
git clone https://github.com/tareq-halaby/sql-powertools.git
cd sql-powertools
composer install
```

1. Configure environment

```bash
cp .env.example .env
# Edit .env as needed
```

1. Serve locally

```bash
php -S localhost:8080 -t .
```

Open <http://localhost:8080>

## 🧭 Usage (Sampling X rows)

1. Connect to MySQL (Step 1) and choose the source database (Step 2).
2. Set “Rows per table (max)” or check “All rows” to omit LIMIT.
3. Pick/create a target database (e.g., `<source>_sample`).
4. In Step 3, choose “Clone sample” and select tables.
5. Optional: enable “Deterministic (ORDER BY PK)” for reproducible samples.
6. Optional: enable “Mask password-like columns” and override per-table columns.
7. Click “Clone Sample” and review the report.

## 🔧 Environment

Copy `.env.example` to `.env` and set the values that fit your setup.

### Example .env

```env
# Admin gate
ADMIN_PASSWORD=change-me-please

# Allow only these IPs (optional, comma-separated)
ALLOWED_IPS=127.0.0.1,::1

# Toggle features/behaviors
READ_ONLY=false            # true disables cloning
DIAGRAM_ENABLED=true       # enable Mermaid ER diagram

# Defaults for Step 1 convenience (no secrets)
DEFAULT_DB_HOST=localhost
DEFAULT_DB_PORT=3306
DEFAULT_DB_USER=

# mysqldump discovery/override
MYSQLDUMP_PATH=            # leave blank to auto-detect or use PATH
```

### Authentication and default password

The app has a simple admin gate to avoid drive-by access on shared machines. Set `ADMIN_PASSWORD` in `.env`. If not set, it defaults to `admin123` for local development — change it.

This tool does not phone home or transmit any data.

## 🛡️ Security

- No passwords in command args (uses `--defaults-extra-file`)
- Security headers (CSP, Referrer-Policy, X-Frame-Options, Permissions-Policy)
- CSRF tokens and session hardening
- Masking auto-detects columns like `password`, `token`, `secret`, `api_key`, etc., plus per-table overrides

## 🧱 Architecture

- Templates: League Plates (`views/`)
- Single entry: `index.php`
- UI: Tailwind via CDN + vanilla JS

## 📄 License

MIT — see `LICENSE`.
