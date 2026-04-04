# Installation Guide

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.3+)
- Composer
- A web server (Apache, Nginx, or WAMP/XAMPP)
- `mysqldump` available in PATH (for export/backup features)

## Quick Start

### 1. Clone the Repository

```bash
git clone https://github.com/tareq-halaby/sql-powertools.git
cd sql-powertools
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

Copy the example environment file and fill in your values:

```bash
cp .env.example .env
```

Edit `.env`:

```env
ADMIN_PASSWORD=your_secure_password_here
```

### 4. Point Your Web Server

Set your web server document root to the project directory and navigate to `http://localhost/` in your browser.

### WAMP / XAMPP

Place the project in your `www` or `htdocs` directory. SQL PowerTools will auto-detect `mysqldump` on WAMP environments.

## Verifying Installation

Once running, you should see the SQL PowerTools login page. Enter the `ADMIN_PASSWORD` you set in `.env` to proceed.
