# syntax=docker/dockerfile:1
#
# Container image for the a2a-php REFERENCE SERVER (examples/complete_a2a_server.php,
# the same server the README's Quick Start runs with `php -S`). The library itself is
# consumed from Packagist (`composer require andreibesleaga/a2a-php`); this image exists
# so the SDK has a runnable, inspectable artifact on ghcr.io and so a full A2A v0.3.0
# endpoint can be started with one `docker run`.
#
# Build:  docker build -t a2a-php .
# Run:    docker run --rm -p 8081:8081 a2a-php
#         curl -s -X POST http://localhost:8081/ -H 'Content-Type: application/json' \
#              -d '{"jsonrpc":"2.0","method":"ping","id":1}'
#
# Multi-stage: dependencies are resolved from the committed composer.lock (no dev
# packages, optimized autoloader) in a builder stage; the runtime stage carries only
# vendor/, src/ and examples/, runs unprivileged, and has a self-contained healthcheck.

# ---------- deps ----------
FROM php:8.3-cli-alpine AS deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction --no-scripts --optimize-autoloader

# ---------- runtime ----------
FROM php:8.3-cli-alpine AS runtime
WORKDIR /app
COPY --from=deps /app/vendor ./vendor
COPY composer.json ./
COPY src ./src
COPY examples ./examples

# The reference server appends its log to ./a2a_server.log (cwd-relative) and keeps
# task storage under /tmp; give the unprivileged user a writable cwd that is NOT the
# code tree, and use it as the document root so no source file is ever served.
RUN mkdir -p /var/a2a && chown www-data:www-data /var/a2a
USER www-data
WORKDIR /var/a2a

# Railway/Fly-style PORT injection; 8081 matches the README examples.
ENV PORT=8081
EXPOSE 8081

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1:".(getenv("PORT")?:"8081")."/.well-known/agent-card.json") ? 0 : 1);'

# Shell form only to expand $PORT; `exec` keeps php as PID 1 so SIGTERM stops it.
CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8081} /app/examples/complete_a2a_server.php"]
