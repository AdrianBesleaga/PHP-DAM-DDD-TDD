# Database Schema

## Entity-Relationship Diagram

```
+------------------+          +--------------------+          +------------------+
|      users       |          |       assets       |          |     folders      |
+------------------+          +--------------------+          +------------------+
| id (PK, INT)     |<---+     | id (PK, INT)       |     +-->| id (PK, INT)     |
| name (VARCHAR)   |    |     | file_name (VARCHAR) |     |   | name (VARCHAR)   |
| email (VARCHAR)  |    +-----| uploaded_by (FK)    |     |   | parent_id (FK)   |--+
| status (VARCHAR) |          | folder_id (FK)      |-----+   | created_by (INT) |  |
| created_at (DT)  |          | status (VARCHAR)    |         | created_at (DT)  |  |
| updated_at (DT)  |          | file_size (INT)     |         | updated_at (DT)  |  |
+------------------+          | mime_type (VARCHAR)  |         +------------------+  |
                              | description (TEXT)   |            ^                  |
    UNIQUE: email             | tags (JSON)          |            +------------------+
                              | created_at (DT)      |            self-referencing FK
                              | updated_at (DT)      |
                              +--------------------+
```

## Table Details

### users

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | INTEGER | PRIMARY KEY | Auto-increment. TODO: migrate to UUID |
| `name` | VARCHAR(255) | NOT NULL | |
| `email` | VARCHAR(255) | NOT NULL, UNIQUE | Stored via custom `EmailType` |
| `status` | VARCHAR(20) | NOT NULL, DEFAULT 'active' | Enum: `active`, `suspended`. Custom `UserStatusType` |
| `created_at` | DATETIME | NOT NULL | Immutable after creation |
| `updated_at` | DATETIME | NULLABLE | Set on any state change via `touch()` |

### assets

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | INTEGER | PRIMARY KEY | Auto-increment. TODO: migrate to UUID |
| `file_name` | VARCHAR(255) | NOT NULL | Validated by `FileName` Value Object |
| `file_size` | INTEGER | NOT NULL | Max 100MB enforced by `FileSize` VO |
| `mime_type` | VARCHAR(100) | NOT NULL | Allowlisted by `MimeType` VO |
| `status` | VARCHAR(20) | NOT NULL, DEFAULT 'draft' | Enum: `draft`, `published`, `archived`. Custom `AssetStatusType` |
| `description` | TEXT | NULLABLE | Required before publishing |
| `tags` | JSON | NOT NULL, DEFAULT '[]' | Max 20 tags, lowercase. TODO: migrate to junction table |
| `folder_id` | INTEGER | NULLABLE, FK → folders(id) | NULL means root level |
| `uploaded_by` | INTEGER | NOT NULL, FK → users(id) | |
| `created_at` | DATETIME | NOT NULL | |
| `updated_at` | DATETIME | NULLABLE | |

### folders

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | INTEGER | PRIMARY KEY | Auto-increment. TODO: migrate to UUID |
| `name` | VARCHAR(255) | NOT NULL | |
| `parent_id` | INTEGER | NULLABLE, FK → folders(id) | NULL = root folder. Self-referencing FK |
| `created_by` | INTEGER | NOT NULL | |
| `created_at` | DATETIME | NOT NULL | |
| `updated_at` | DATETIME | NULLABLE | |

## Custom Doctrine Types

| Type Class | Converts | DB Column | PHP Type |
|-----------|----------|-----------|----------|
| `EmailType` | `Email` Value Object | VARCHAR(255) | `Email` |
| `AssetStatusType` | `AssetStatus` Enum | VARCHAR(20) | `AssetStatus` |
| `UserStatusType` | `UserStatus` Enum | VARCHAR(20) | `UserStatus` |

## Migration Strategy

### Current (Development)
```bash
composer db:create    # Drops and recreates all tables via SchemaTool
```

### Production Recommendation
Use Doctrine Migrations for versioned, reversible schema changes:

```bash
# Generate a migration from entity diff
vendor/bin/doctrine-migrations diff

# Run pending migrations
vendor/bin/doctrine-migrations migrate

# Rollback last migration
vendor/bin/doctrine-migrations migrate prev
```

Each migration is a PHP class with `up()` and `down()` methods, version-tracked in a `doctrine_migration_versions` table.

## Known Schema Limitations

1. **ID Generation**: `MAX(id) + 1` is not atomic under concurrent access. Migrate to UUIDs or database sequences.
2. **Tags as JSON**: `LIKE '%"tag"%'` is fragile. Migrate to an `asset_tags(asset_id, tag)` junction table.
3. **No Foreign Key enforcement**: SQLite has FK support but it must be enabled per-connection (`PRAGMA foreign_keys = ON`).
