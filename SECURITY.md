# Security Policy

## Supported Versions

The following versions of SQL PowerTools currently receive security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability in SQL PowerTools, please **do not** open a public GitHub issue.

Instead, report it privately by emailing:

> **security@tareq.im**

Please include the following in your report:

- A clear description of the vulnerability
- Steps to reproduce the issue
- The potential impact (e.g., data exposure, privilege escalation)
- Any suggested mitigation or fix (optional)

## Response Timeline

- **Acknowledgement**: Within 48 hours
- **Status update**: Within 7 days
- **Patch release**: Dependent on severity — critical issues are prioritized

## Security Design Notes

SQL PowerTools is designed with security as a core principle:

- No credentials are passed as command-line arguments (uses `--defaults-extra-file`)
- All database credentials are session-scoped and never stored in plaintext
- CSRF tokens protect all state-changing requests
- Security headers (CSP, Referrer-Policy, X-Frame-Options, Permissions-Policy) are set on every response
- Sensitive columns (e.g., `password`, `token`, `secret`, `api_key`) are auto-detected and masked by default
- Admin access is protected by a configurable password gate (`ADMIN_PASSWORD` in `.env`)
