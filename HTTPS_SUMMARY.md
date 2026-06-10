# HTTPS/TLS Integration Summary

## Completion status

The HTTPS/TLS enhancements are active and do not alter the existing HTTP
behaviour. All A2A TCK categories continue to pass — see
`docs/protocol-compliance.md` for the current per-category results against
the pinned TCK version (`docs/tck-upgrade.md`).

> `https_a2a_server.php` is a **demo** entry point. Production deployments
> must terminate TLS at a hardened reverse proxy — see `SECURITY.md`.

## What changed

### Security

- Added TLS-aware entry point (`https_a2a_server.php`).
- Applied default security headers (HSTS, nosniff, frame options) in production mode.
- Introduced optional HTTPS enforcement via `A2A_FORCE_HTTPS` and proxy detection through `X-Forwarded-Proto`.

### Compatibility

- HTTP-only workflows remain unchanged.
- The same JSON-RPC handlers operate in both modes.
- Agent card, streaming endpoints, and push notification APIs keep their original semantics.

## Quick start

```bash
# Development (HTTP)
php -S localhost:8081 https_a2a_server.php

# Production-style mode (expects TLS termination upstream)
A2A_MODE=production php -S localhost:8081 https_a2a_server.php

# Interactive helper
./start_https_server.sh
```

## Deployment in production

1. Terminate TLS with nginx or Apache (examples are documented in `A2A_HTTPS_IMPLEMENTATION.md`).
2. Forward requests to the PHP built-in server or your preferred PHP-FPM stack.
3. Validate security headers with `curl -I https://your-host/.well-known/agent-card.json`.
4. Monitor `a2a_server.log` for HTTPS flag entries to confirm proxy detection.

## Benefits

- Encrypted transport for production deployments.
- Security headers applied automatically in production mode.
- Zero change to the core protocol implementation.
- Straightforward migration path from HTTP to HTTPS.

