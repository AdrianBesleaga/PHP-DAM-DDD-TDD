# ─── Stage 1: Builder ────────────────────────────────────────────
# Install dependencies in a full PHP image, then discard it.
FROM php:8.4-cli AS builder

# Install system dependencies for Composer + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy dependency files first (Docker layer caching)
COPY composer.json composer.lock ./

# Install production dependencies only (no dev tools)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader \
    --prefer-dist

# Copy application source
COPY . .

# ─── Stage 2: Production ────────────────────────────────────────
# Tiny Alpine image with only what's needed to run.
FROM php:8.4-fpm-alpine AS production

# Install SQLite (our demo database)
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite

# Security: run as non-root user
RUN addgroup -g 1000 appuser && adduser -u 1000 -G appuser -D appuser

WORKDIR /app

# Copy vendor and source from builder (no dev dependencies, no git)
COPY --from=builder --chown=appuser:appuser /app/vendor ./vendor
COPY --chown=appuser:appuser src/ ./src/
COPY --chown=appuser:appuser config/ ./config/
COPY --chown=appuser:appuser public/ ./public/
COPY --chown=appuser:appuser bin/ ./bin/
COPY --chown=appuser:appuser composer.json ./

# Create writable directories
RUN mkdir -p var/logs var && chown -R appuser:appuser var/

USER appuser

EXPOSE 9000

# Health check for container orchestration (K8s, ECS, Docker)
HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD php -r "echo 'healthy';" || exit 1

CMD ["php-fpm"]
