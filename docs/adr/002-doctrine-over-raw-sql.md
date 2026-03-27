# ADR-002: Doctrine ORM over Raw SQL

## Status
Accepted

## Context
We needed a persistence strategy that provides:
- ACID transactions for data integrity
- Protection against SQL injection
- Entity mapping without manual hydration
- Database portability (SQLite for dev, PostgreSQL/MySQL for production)

Alternatives considered:
- **Raw PDO**: Maximum control but requires manual query building, hydration, and escaping
- **Query Builder only (DBAL)**: Less boilerplate than PDO but no entity mapping
- **Doctrine ORM**: Full ORM with Unit of Work, identity map, and entity lifecycle

## Decision
We chose **Doctrine ORM 3.x** because:

1. **ACID by default** — Unit of Work batches all changes into one transaction on `flush()`
2. **SQL Injection prevention** — All queries use parameterized bindings via DQL/QueryBuilder
3. **DDD alignment** — Entities map directly to Doctrine entities with minimal annotations
4. **Same interface** — `DoctrineUserRepository` implements the same `UserRepositoryInterface` as `InMemoryUserRepository`

## Consequences

**Positive:**
- Zero SQL injection risk on standard CRUD operations
- Transparent transaction management (no manual BEGIN/COMMIT)
- Custom Doctrine Types map Value Objects (Email, AssetStatus) to DB columns natively

**Negative:**
- Performance overhead for bulk operations (Unit of Work tracks all entities)
- Custom LIKE queries (e.g., `findByTag`) still need manual wildcard escaping
- Learning curve for Doctrine's entity lifecycle and proxy objects
- The `nextId()` pattern using `MAX(id)+1` is not atomic — needs UUID migration for production
