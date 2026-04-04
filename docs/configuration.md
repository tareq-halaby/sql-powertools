# Configuration Reference

SQL PowerTools is configured via environment variables set in your `.env` file.

## Environment Variables

| Variable | Required | Default | Description |
|---|---|---|---|
| `ADMIN_PASSWORD` | Yes | — | Password to access the SQL PowerTools dashboard |

## `.env.example`

An example configuration file is included at `.env.example`. Copy it to `.env` to get started:

```bash
cp .env.example .env
```

## Session Configuration

Database credentials are **session-scoped** — they are never written to disk or stored in plaintext. Once you close the browser tab, the connection details are cleared.

## `mysqldump` Path

SQL PowerTools attempts to auto-discover `mysqldump` in the following locations:

1. System `PATH`
2. Common WAMP installation paths (e.g., `C:\wamp64\bin\mysql\...`)

If auto-detection fails, an error will be shown on the export/backup screen. Ensure `mysqldump` is accessible from the web server's environment.

## Sensitive Column Masking

By default, columns with names matching patterns like `password`, `token`, `secret`, `api_key`, `hash`, or `salt` are masked in the UI. This can be overridden on a per-table basis via the table settings panel.
