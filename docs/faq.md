# Frequently Asked Questions

## General

### What is SQL PowerTools?

SQL PowerTools is a lightweight, self-hosted PHP web application for safely cloning, exporting, and backing up MySQL databases. It prioritizes security by masking sensitive columns and never exposing credentials.

### Is it safe to expose SQL PowerTools to the internet?

Not recommended without additional security measures such as IP allowlisting, VPN access, or a reverse proxy with authentication. It is designed for local or internal network use.

### Does it support MariaDB?

Yes. SQL PowerTools works with MariaDB 10.3+. Any MySQL-compatible database should work.

## Installation

### Why is `mysqldump` not found?

SQL PowerTools auto-discovers `mysqldump` from system PATH and common WAMP paths. If it isn't found, make sure `mysqldump` is installed and accessible from your web server's environment. See [docs/installation.md](./installation.md) for details.

### Can I run it on shared hosting?

Generally no. Shared hosting environments typically do not allow `mysqldump` access or persistent PHP sessions in the required way.

## Security

### Are my database passwords stored?

No. Credentials are stored only in the PHP session and are cleared when the session ends.

### What does sensitive column masking do?

It hides the values of columns with names like `password`, `token`, or `api_key` in the UI. The data itself is not modified in the database.

## Contributing

### How do I contribute?

See [CONTRIBUTING](../CONTRIBUTING.md) — fork the repo, create a branch, and open a pull request.
