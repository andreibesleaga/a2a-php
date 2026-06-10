# Changelog

All notable changes to this project are documented in this file.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning: [SemVer](https://semver.org/).

## [Unreleased]

### Added
- Push notification webhook **delivery** (A2A v0.3.0 §9.5): task snapshots
  are POSTed to the configured webhook with `X-A2A-Notification-Token` and
  `Authorization` headers on task state changes.
- Opt-in `A2A_WEBHOOK_ALLOWLIST` env var (comma-separated hostnames) as an
  SSRF guard for stored webhook configs; unset preserves prior behavior.
- `A2A\Notifications\PushNotifier` service and
  `A2A\Utils\HttpClient::postNotification()`.
- `src/Handlers/v030/` request-handler architecture with `HandlerRegistry`
  (public `A2AProtocol_v030` API unchanged).
- CI (GitHub Actions): PHP 8.2/8.3/8.4 matrix running lint, PSR-12,
  PHPStan level 8, `composer audit`, PHPUnit, plus the official A2A TCK
  pinned at `0.3.0.beta5`.
- Release workflow attaching a CycloneDX SBOM to GitHub releases.
- 58 new tests: webhook delivery (unit, protocol, e2e), JSON-RPC
  negative-envelope corpus, A2A §5.1 message-field validation, handler
  registry, and an end-to-end suite that boots the real reference server
  plus a webhook receiver.
- Governance: `SECURITY.md`, `CONTRIBUTING.md`, `CHANGELOG.md`,
  Dependabot config, ADRs (`docs/adr/`), `docs/tck-upgrade.md`,
  `docs/UPGRADE-1.0.md`.
- `roave/security-advisories` (require-dev) to fail installs with
  known-CVE dependencies.

### Fixed
- Messages missing required fields (`messageId`, `role`, `parts`) are now
  rejected with JSON-RPC `-32602` and never leak PHP warnings into the
  HTTP response body (TCK `test_missing_required_message_fields`).
- `A2A\Exceptions\{TaskNotCancelable,PushNotificationNotSupported,
  UnsupportedOperation,ContentTypeNotSupported,InvalidAgentResponse}Exception`
  could never be autoloaded (all five classes lived in one
  `A2ASpecificErrors.php` file that PSR-4 cannot map); each class now has
  its own file.
- `examples/compliant_server.php` referenced a non-existent
  `examples/vendor/autoload.php`.
- Example servers no longer emit PHP warnings into protocol output
  (`display_errors=0`).

### Changed
- `composer.json`: `php` constraint corrected from `^8.0` to `^8.2`
  (the `illuminate/* ^12` dependencies already required PHP 8.2+, so
  8.0/8.1 installs could never resolve).
- `grpc/grpc` moved from `require` to `suggest`: `A2A\Client\GrpcClient`
  is an explicit stub and the package forced an unused PECL-extension
  dependency on every consumer.
- Codebase formatted to PSR-12 (`phpcs.xml.dist`); PHPStan level 8
  enforced with a ratchet baseline.

### Compliance
- Official `a2a-tck@0.3.0.beta5`: **all categories pass** (mandatory 32,
  capability 39, quality 14, features 15; capability honesty "excellent").
  Note: the previously advertised 66/66 figure was measured against an
  older TCK snapshot; the kit has since grown (e.g. webhook delivery
  tests), and this release passes the enlarged suite in full.
