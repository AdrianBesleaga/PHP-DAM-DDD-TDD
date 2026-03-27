# ADR-007: Docker Containerization Strategy

## Status
Accepted

## Context
The application needs to be deployable in any environment (local, CI, staging, production) with consistent behavior. Options considered:

- **Bare metal / VM**: Manual PHP installation, environment-specific configs
- **Single-stage Docker**: Simple but produces large images (~500MB)
- **Multi-stage Docker**: Optimized for production, small images (~50MB)
- **Platform-as-a-Service (Heroku, Railway)**: Zero-ops but less control

## Decision
We chose **multi-stage Docker builds** with the following architecture:

### Build Stage (`php:8.3-cli`)
- Installs Composer and system dependencies
- Runs `composer install --no-dev` (production deps only)
- Discarded after build — dev tools never reach production

### Production Stage (`php:8.3-fpm-alpine`)
- Alpine-based image (~5MB base)
- Copies only vendor + source from build stage
- Runs as non-root user (`appuser`)
- Built-in HEALTHCHECK instruction

### Docker Compose (local development)
- `app`: PHP-FPM with source volume mounts (live reload)
- `nginx`: Reverse proxy (mirrors production architecture)
- Named volume for persistent SQLite + logs

### CI/CD (GitHub Actions)
```
PR/Push → Tests ──┐
        → PHPStan ├──→ Docker Build
        → CS-Fix  ┘
```
Quality checks run in parallel. Docker build only runs after all pass.

## Consequences

**Positive:**
- Consistent environment across all stages (dev = staging = production)
- ~50MB production image (vs ~500MB without multi-stage)
- Non-root user prevents container escape attacks
- CI catches build failures before merge
- `make` commands standardize developer workflow

**Negative:**
- Docker adds complexity for developers unfamiliar with containers
- Volume mounts on macOS have I/O overhead (mitigated by `:ro`)
- SQLite in a container volume is not suitable for horizontal scaling (needs PostgreSQL)
