# A2A Protocol HTTPS/TLS Implementation

## Overview

The reference server includes optional HTTPS-aware behaviour that lets operators terminate TLS in front of the PHP process while preserving full backward compatibility with the existing HTTP flow. The implementation introduces security headers, environment-based mode detection, and documentation for deploying behind nginx or Apache.

## Test status

HTTPS orchestration reuses the same protocol handlers as the HTTP server,
so compliance remains unchanged — see `docs/protocol-compliance.md` for the
current per-category TCK results against the pinned kit version
(`docs/tck-upgrade.md`).

> `https_a2a_server.php` is a **demo** entry point; production deployments
> must terminate TLS at a hardened reverse proxy (see `SECURITY.md`).

## Key additions

- HTTPS-aware server entry point (`https_a2a_server.php`) that detects production mode and applies security headers.
- Self-signed certificate helper invoked by `start_https_server.sh` for local testing.
- Support for the `A2A_MODE=production` environment variable to toggle strict security behaviour.
- Logging extensions that capture whether a request arrived via HTTP or HTTPS (based on proxy headers).
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

### Interactive helper

```bash
./start_https_server.sh
```

The script guides users through generating self-signed certificates, configuring environment variables, and starting the server.

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
- `X-Content-Type-Options: nosniff` and `X-Frame-Options: DENY` headers for clickjacking and MIME hardening.
- Proxy detection through `X-Forwarded-Proto` to mark requests as HTTPS in logs.
- Ability to force HTTPS redirects with `A2A_FORCE_HTTPS=true` when using the helper script.

## Monitoring

- Application logs note whether HTTPS was detected: `"https": true` entries confirm TLS termination upstream.
- Use `curl -v https://a2a.example.com/.well-known/agent-card.json` to verify certificate chains and headers.
- Rotate and renew certificates with your preferred tooling (for example, `certbot` for Let's Encrypt).

These adjustments provide a clear path to deploying the A2A PHP server in secure environments without altering protocol behaviour.
