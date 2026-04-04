# Security Model

This document describes the security architecture of SQL PowerTools.

## Authentication

Access to the dashboard is controlled by a single `ADMIN_PASSWORD` environment variable. This password is never stored on disk; it is compared server-side using a constant-time comparison to prevent timing attacks.

## Credential Handling

- Database credentials (host, port, username, password) are stored **in the PHP session only**
- They are never written to disk, logged, or included in error messages
- Sessions expire when the browser tab is closed
- No credentials are passed as command-line arguments; `mysqldump` is invoked using `--defaults-extra-file` with a temporary file that is deleted immediately after use

## CSRF Protection

All state-changing requests (clone, export, backup) include a CSRF token that is:
- Generated per-session using `random_bytes(32)`
- Verified server-side before any operation is executed
- Invalid if the session is expired or tampered with

## HTTP Security Headers

Every response includes the following headers:

| Header | Value |
|---|---|
| `Content-Security-Policy` | Restricts script sources to self |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `no-referrer` |
| `Permissions-Policy` | Disables camera, mic, geolocation |

## Sensitive Data Masking

Columns with sensitive names are masked in the UI by default. The list includes: `password`, `passwd`, `token`, `secret`, `api_key`, `apikey`, `hash`, `salt`, `ssn`, `credit_card`.

This masking is purely visual — the underlying data is not altered.
