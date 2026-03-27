# ADR-006: JWT Authentication as Middleware

## Status
Accepted

## Context
The API needed authentication to protect write operations and user-specific data. Options considered:

- **Session-based auth**: Requires server-side state, doesn't scale horizontally
- **API keys**: Simple but no expiry, no user claims
- **JWT tokens**: Stateless, carry user claims, standard in REST/GraphQL APIs
- **OAuth2 / OpenID Connect**: Full-featured but heavyweight for this project

## Decision
We chose **JWT (HS256)** implemented as **PSR-15 middleware**:

1. **Why JWT**: Stateless tokens that carry user identity (`sub` claim) — no server-side session storage needed
2. **Why middleware**: Authentication is a cross-cutting concern — it doesn't belong in controllers or domain logic
3. **Why env secrets**: JWT secret is loaded from `$_ENV['JWT_SECRET']` via `.env` file (vlucas/phpdotenv). Never hardcoded.
4. **Why HS256**: Symmetric signing is sufficient for a single-service API. For microservices, RS256 (asymmetric) would be preferred.

### Route protection:
- **Protected**: All `/api/*` endpoints require `Authorization: Bearer <token>`
- **Public**: `/` (API index), `/graphql` (configurable)
- **Preflight**: `OPTIONS` requests bypass auth (CORS compatibility)

### Request enrichment:
On successful validation, the middleware injects claims into request attributes:
```php
$request->getAttribute('auth_user_id')  // → 42
$request->getAttribute('auth_claims')   // → ['sub' => 42, 'iat' => ..., 'exp' => ...]
```

## Consequences

**Positive:**
- Controllers are auth-unaware — they just read `$request->getAttribute('auth_user_id')`
- Adding new protected routes requires zero auth code
- Stateless — scales horizontally without shared session store
- 401/403 responses are consistent JSON, handled by the middleware

**Negative:**
- Tokens cannot be revoked without a blocklist (trade-off for statelessness)
- HS256 shares the same secret for signing and verification — unsuitable for multi-service architectures
- No role-based access control (RBAC) — would need a `roles` claim and authorization middleware
