# Security Policy

## Supported Versions

Only the latest released major/minor version of `andreibesleaga/a2a-php`
receives security fixes.

## Reporting a Vulnerability

Please report vulnerabilities privately via
[GitHub Security Advisories](https://github.com/andreibesleaga/a2a-php/security/advisories/new)
for this repository (preferred), or by contacting the maintainer directly.
**Do not open public issues for security reports.**

You can expect an acknowledgment within 7 days. Coordinated disclosure is
appreciated; credit is given in release notes unless you prefer otherwise.

## Scope and Deployment Notes

- `examples/*.php` and `https_a2a_server.php` are **demo/reference servers**,
  not production runtimes. Run production deployments behind a hardened
  reverse proxy (nginx/Apache/Caddy) that terminates TLS — see
  `A2A_HTTPS_IMPLEMENTATION.md`.
- `A2A_DEMO_AUTH_TOKEN` is a demo credential gate for the authenticated
  extended agent card. Use a real authentication layer in production.
- Push notification webhooks are an SSRF vector by design. Set
  `A2A_WEBHOOK_ALLOWLIST` (comma-separated hostnames) in any deployment that
  accepts push notification configs from untrusted parties; when unset, any
  webhook URL is accepted.
- Supply-chain: `composer.lock` is committed, `roave/security-advisories`
  blocks installs with known-CVE dependencies, and CI runs `composer audit`.
