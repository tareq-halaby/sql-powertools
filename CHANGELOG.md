# Changelog

All notable changes to SQL PowerTools will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- FAQ documentation for common user questions
- Enhanced security policy documentation
- GitHub Actions CI (Composer validate + PHP lint across 8.1–8.4)
- Full environment variable reference in configuration docs

### Fixed
- Align docs with code: use `ALLOW_IPS` (not `ALLOWED_IPS`)
- Clarify `.env.example` defaults for local development
- Require PHP 8.1+ (matches `readonly` constructor properties in source)
- CI: use `actions/checkout@v5` (Node 24), pin `ubuntu-24.04`, add PHP 8.4

## [1.0.0] - 2025-01-01

### Added
- Initial release of SQL PowerTools
- MySQL database cloning functionality
- Sensitive data masking
- Session-backed credentials
- Secure mysqldump execution
- Sample export with configurable row limits
- Database backup features
