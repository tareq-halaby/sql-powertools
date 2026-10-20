# Configuration Reference

SQL PowerTools is configured via environment variables in your `.env` file.

## Environment Variables

| Variable | Required | Default | Description |
|---|---|---|---|
| `ADMIN_PASSWORD` | Recommended | `admin123` | Password for the dashboard login gate |
| `ALLOW_IPS` | No | _(empty = allow all)_ | Comma-separated IP allowlist |
| `READ_ONLY` | No | `false` | When `true`, cloning is disabled |
| `DIAGRAM_ENABLED` | No | `true` | Show the Mermaid ER diagram button |
| `DEFAULT_DB_HOST` | No | `localhost` | Pre-fill host on Step 1 |
| `DEFAULT_DB_PORT` | No | `3306` | Pre-fill port on Step 1 |
| `DEFAULT_DB_USER` | No | _(empty)_ | Pre-fill username on Step 1 |
| `MYSQLDUMP_PATH` | No | `mysqldump` | Absolute path when `mysqldump` is not on `PATH` |

## `.env.example`

```bash
cp .env.example .env
```

Edit `.env` and set at least `ADMIN_PASSWORD` before exposing the app.

## Session Configuration

Database credentials are **session-scoped** — they are never written to disk. Closing the browser session clears connection details.

## `mysqldump` Path

SQL PowerTools resolves `mysqldump` in this order:

1. `MYSQLDUMP_PATH` from the environment
2. System `PATH`
3. Common WAMP installation paths on Windows

If discovery fails, export/backup actions will show an error. Ensure the web server process can execute `mysqldump`.

## Sensitive Column Masking

Columns with names matching patterns like `password`, `token`, `secret`, `api_key`, `hash`, or `salt` are masked by default. Override per table in the UI when needed. Patterns live in `config/masking.php`.
