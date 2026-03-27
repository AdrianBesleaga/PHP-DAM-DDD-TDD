# ADR-005: SQL Injection Prevention Strategy

## Status
Accepted

## Context
SQL injection is consistently ranked in the OWASP Top 10. Our DAM system accepts user input (names, emails, tags, folder names) that is stored in a database. We needed a defense-in-depth strategy.

## Decision
We use four layers of defense:

### Layer 1: Value Object Validation (Domain)
All user input passes through Value Objects before reaching the database:
- `Email` validates format via `filter_var()`
- `FileName` rejects empty or whitespace-only values
- `FileSize` rejects negative or oversized values
- `MimeType` uses an explicit allowlist

Invalid input is rejected at the domain boundary — it never reaches SQL.

### Layer 2: Doctrine ORM Parameterized Queries (Infrastructure)
All standard CRUD uses Doctrine's QueryBuilder or DQL, which automatically uses prepared statements with parameter binding:

```php
->setParameter('tag', $value)  // Always parameterized, never concatenated
```

### Layer 3: LIKE Wildcard Escaping (Infrastructure)
Custom LIKE queries escape `%` and `_` characters in user input:

```php
$escapedTag = str_replace(['%', '_'], ['\\%', '\\_'], $tag);
```

Without this, a tag value of `%` would match ALL records.

### Layer 4: No Raw SQL Policy
Raw SQL (`$connection->executeQuery('SELECT ...')`) is prohibited. All database access must go through Doctrine's QueryBuilder or DQL.

## Consequences

**Positive:**
- Zero SQL injection surface on standard operations
- Value Objects catch malicious input before it reaches infrastructure
- Doctrine handles escaping transparently for 99% of queries

**Negative:**
- LIKE wildcard escaping must be done manually for each custom query
- Full-text search on JSON columns (tags) should migrate to a junction table for proper query safety
