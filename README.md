# SQL PowerTools

SQL PowerTools is a lightweight PHP web app to safely clone, export, and back up MySQL databases with security-first features like sensitive data masking, session-backed credentials, and secure mysqldump execution.

## Features
- Clone structure and copy a sampled subset of rows from selected tables (X rows per table)
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

## Usage (Sampling X rows)
1. Connect to MySQL (Step 1) and choose the source database (Step 2).
2. Set "Rows per table (max)" to the sample size X.
3. Pick or create a target database (e.g., `<source>_sample`).
4. In Step 3, choose "Clone sample", then select tables to include.
5. Optional: Enable "Deterministic (ORDER BY PK)" for reproducible samples.
6. Optional: Enable "Mask password-like columns" and adjust per-table overrides.
7. Click "Clone Sample". A report will show what was created and how many rows were copied.

## Environment
Copy `.env.example` to `.env` and set:
- `APP_SECRET`: CSRF/session secret
- `ALLOWED_IPS`: Comma-separated allowlist (optional)

### Authentication and Default Password
This app includes a simple admin gate to prevent drive-by access on shared machines. The password is read from the environment variable `ADMIN_PASSWORD`. If not provided, it defaults to `admin123` for local development convenience.

- Change it by setting `ADMIN_PASSWORD` in your environment or `.env`.
- Example `.env`:
  ```env
  APP_SECRET=change-me
  ADMIN_PASSWORD=super-strong-password
  ALLOWED_IPS=127.0.0.1
  ```
- Purpose: to protect access to database operations in dev/test. It does not "phone home" or transmit data anywhere.
- Recommendation: always set a strong `ADMIN_PASSWORD` and restrict access via `ALLOWED_IPS` in shared/staging environments.

Credentials for MySQL are entered in the UI and stored in the session for the current flow.

## Security
- No passwords in command args; uses `--defaults-extra-file`
- Security headers: CSP, Referrer-Policy, X-Frame-Options, Permissions-Policy
- CSRF tokens and session hardening
- Masking: auto-detects columns like `password`, `token`, `secret`, `api_key`, etc., and supports per-table overrides
- Admin gate: requires the `ADMIN_PASSWORD` to access the app (default `admin123` only for local dev; override in `.env`).

## Development
- Templates: League Plates (`views/`)
- PHP single entry: `index.php`
- Frontend: Tailwind via CDN, vanilla JS

## License
MIT. See `LICENSE`.



