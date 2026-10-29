# A2A Protocol HTTPS/TLS Implementation

## Overview

The reference server includes optional HTTPS-aware behaviour that lets operators terminate TLS in front of the PHP process while preserving full backward compatibility with the existing HTTP flow. The implementation introduces an HSTS header, environment-based mode detection, and documentation for deploying behind nginx or Apache.

## Test status

HTTPS orchestration reuses the same protocol handlers as the HTTP server,
so compliance remains unchanged — see `docs/protocol-compliance.md` for the
current per-category TCK results against the pinned kit version
(`docs/tck-upgrade.md`).

> `https_a2a_server.php` is a **demo** entry point; production deployments
> must terminate TLS at a hardened reverse proxy (see `SECURITY.md`).

## Key additions

- HTTPS-aware server entry point (`https_a2a_server.php`) that detects HTTPS mode and, in that mode, sends a `Strict-Transport-Security` header.
- Self-signed certificate generation inside `https_a2a_server.php` when HTTPS mode starts (needs `ext-openssl`; writes `/tmp/a2a_certs/server.key` and `server.crt`). The PHP built-in server does not use these files for TLS.
- Support for the `A2A_MODE=production` environment variable to switch on HTTPS mode (HSTS header, certificate generation). HTTPS mode is also on when `$_SERVER['HTTPS']` is set or the request arrives on port 8443.
- Logging extensions that record whether the server runs in HTTP or HTTPS mode (the mode detection above; proxy headers are not read).
- Documentation covering nginx and Apache reverse-proxy setups.

## Running the server

### Development (HTTP)

```bash
php -S localhost:8081 https_a2a_server.php
```

### Production-style execution

```bash
A2A_MODE=production php -S localhost:8081 https_a2a_server.php
```

> The PHP built-in server does not provide native TLS. For encrypted traffic, place nginx or Apache in front and forward requests to the PHP process.
>
> `https_a2a_server.php` keeps tasks in an in-memory `Storage('array')`, so under `php -S` a task does not survive to the next request (checked 29 Oct 2026: `tasks/get` after `message/send` returned `-32001`). It is a demo of the HTTPS handling, not of persistence.

### Interactive helper

```bash
./start_https_server.sh
```

The script is a menu: start the HTTP server on port 8081, start the HTTPS-mode server on port 8443 (`A2A_MODE=production`), run tests against both, compare HTTP and HTTPS, or print a production certificate guide. It does not generate certificates itself.

## Reverse-proxy configuration

### nginx example

```nginx
server {
    listen 443 ssl http2;
    server_name a2a.example.com;

    ssl_certificate /etc/letsencrypt/live/a2a.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/a2a.example.com/privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }
}

server {
    listen 80;
    server_name a2a.example.com;
    return 301 https://$host$request_uri;
}
```

### Apache example

```apache
<VirtualHost *:443>
    ServerName a2a.example.com

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/a2a.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/a2a.example.com/privkey.pem

    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY

    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8081/
    ProxyPassReverse / http://127.0.0.1:8081/
</VirtualHost>

<VirtualHost *:80>
    ServerName a2a.example.com
    Redirect permanent / https://a2a.example.com/
</VirtualHost>
```

## Security features

- HSTS (`Strict-Transport-Security`) enabled in production mode.
- `X-Content-Type-Options: nosniff` and `X-Frame-Options: DENY` headers for clickjacking and MIME hardening are set by the nginx and Apache examples above, not by `https_a2a_server.php`.
- `X-Forwarded-Proto` is not read. Behind a TLS-terminating proxy, set `A2A_MODE=production` so that requests are logged as HTTPS.
- When the server is not in HTTPS mode, `A2A_FORCE_HTTPS=true` makes it answer with a 301 redirect to `https://<host>:8443`.
- The demo sends `Access-Control-Allow-Origin: *` on every response.

## Monitoring

- Application logs note whether HTTPS mode is on: `"https": true` entries show the mode, not that TLS is terminated upstream.
- Use `curl -v https://a2a.example.com/.well-known/agent-card.json` to verify certificate chains and headers.
- Rotate and renew certificates with your preferred tooling (for example, `certbot` for Let's Encrypt).

These adjustments provide a clear path to deploying the A2A PHP server in secure environments without altering protocol behaviour.
