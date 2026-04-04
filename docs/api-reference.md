# API Reference

## Overview

SQL PowerTools provides a web-based interface for database operations. This document describes the available endpoints and their parameters.

## Endpoints

### POST /clone

Clone a database structure with optional data sampling.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| source_db | string | Yes | Source database name |
| target_db | string | Yes | Target database name |
| sample_rows | integer | No | Number of rows to sample (default: 100) |
| mask_sensitive | boolean | No | Enable sensitive data masking (default: true) |

**Response:**
```json
{
  "success": true,
  "message": "Database cloned successfully",
  "tables_cloned": 15,
  "rows_sampled": 1500
}
```

### POST /export

Export a database to SQL format.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| database | string | Yes | Database name to export |
| format | string | No | Export format: sql, csv (default: sql) |

### POST /backup

Create a secure backup using mysqldump.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| database | string | Yes | Database name to backup |
| compress | boolean | No | Compress output (default: false) |

## Authentication

All requests require session-backed credentials. Ensure you are logged in before making API calls.

## Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - Session expired |
| 500 | Internal Server Error |
