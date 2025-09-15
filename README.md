# SQL PowerTools

SQL PowerTools is a lightweight PHP web app to safely clone, export, and back up MySQL databases with security-first features like sensitive data masking, session-backed credentials, and secure mysqldump execution.

## Features
- Clone structure and copy a sampled subset of rows
- Deterministic sampling (ORDER BY primary key)
- Full backups (schema + data) with optional masking
- Sensitive column auto-detection and per-table overrides
- Secure mysqldump via temporary defaults file (no creds in args)
- Session-backed DB credentials; security headers; CSRF protection
- Dark mode UI with modern, consistent UX

## Requirements
- PHP 8.0+
- MySQL 5.7+/8.x with access to `information_schema`
- `mysqldump` available in PATH

## Quick Start
1. Clone the repo and install dependencies.
```bash
git clone https://github.com/<your-org>/sql-powertools.git
cd sql-powertools
composer install
```
2. Configure environment.
```bash
cp .env.example .env
# Edit .env as needed
```
3. Serve locally (e.g., built-in PHP server):
```bash
php -S localhost:8080 -t .
```
Visit `http://localhost:8080`.

## Environment
Copy `.env.example` to `.env` and set:
- `APP_SECRET`: CSRF/session secret
- `ALLOWED_IPS`: Comma-separated allowlist (optional)

Credentials for MySQL are entered in the UI and stored in the session for the current flow.

## Security
- No passwords in command args; uses `--defaults-extra-file`
- Security headers: CSP, Referrer-Policy, X-Frame-Options, Permissions-Policy
- CSRF tokens and session hardening
- Masking: auto-detects columns like `password`, `token`, `secret`, `api_key`, etc., and supports per-table overrides

## Development
- Templates: League Plates (`views/`)
- PHP single entry: `index.php`
- Frontend: Tailwind via CDN, vanilla JS

## License
MIT. See `LICENSE`.



