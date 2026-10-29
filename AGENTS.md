# AGENTS.md

Short guide for AI coding agents and new contributors. `README.md` explains the
project to users, `CONTRIBUTING.md` holds the pull request process. This file
only collects what a change needs to know first. If it disagrees with the README,
the README wins; fix this file.

## What this repository is

A PHP SDK for the A2A (Agent-to-Agent) Protocol **v0.3.0**: JSON-RPC 2.0 over
HTTP POST, Server-Sent Events for streaming, push notification webhooks, and a
runnable reference server. Composer package `andreibesleaga/a2a-php`, PHP `^8.2`,
namespace `A2A\` mapped to `src/`. The v1.0 protocol is not implemented; see
`docs/UPGRADE-1.0.md`.

## Layout

| Path | Purpose |
| ---- | ------- |
| `src/A2AServer.php`, `src/A2AProtocol_v030.php` | Entry points: validate JSON-RPC and dispatch |
| `src/Handlers/v030/` | One handler class per JSON-RPC method, plus `HandlerRegistry` |
| `src/Models/`, `src/Models/v030/` | Protocol models; `v030/` holds the version-scoped ones (ADR 0003) |
| `src/TaskManager.php`, `src/PushNotificationManager.php`, `src/Storage/` | Task and webhook-config persistence |
| `src/Notifications/PushNotifier.php` | Webhook delivery and the `A2A_WEBHOOK_ALLOWLIST` check |
| `src/Streaming/`, `src/Events/`, `src/Execution/` | SSE output, event bus, agent executor |
| `src/A2AClient.php`, `src/Client/` | Client side (`GrpcClient` is a stub) |
| `examples/` | Demo programs; `complete_a2a_server.php` is the reference server used by the TCK |
| `tests/` | PHPUnit suite, including `tests/E2E/` (boots the reference server) |
| `docs/` | API reference, TCK status and upgrade procedure, ADRs, v1.0 plan |

`ARCHITECTURE.md` has the diagrams.

## Commands

```bash
composer install
composer test                  # PHPUnit: unit, integration and e2e
composer analyse               # PHPStan level 8 with a baseline
vendor/bin/phpcs -n            # PSR-12, the command CI runs
composer validate --strict
composer audit
php -S localhost:8081 examples/complete_a2a_server.php   # reference server
```

The official TCK run is described in `README.md` (section Testing) and
`docs/tck-upgrade.md`.

## Rules for changes

- The public API of the v0.3.0 implementation does not change
  (`CONTRIBUTING.md`, ground rule 1). Breaking work belongs to the future v1.0
  namespace.
- The pinned TCK (`A2A_TCK_REF` in `.github/workflows/ci.yml`) stays green. A pin
  change is its own pull request (`docs/tck-upgrade.md`).
- Do not grow `phpstan-baseline.neon`; fix the finding.
- Every behaviour change comes with golden-path, edge-case and error-path tests.
- Update the docs in the same change: methods table in `README.md`, schemas and
  error codes in `docs/api-reference.md`, TCK counts in
  `docs/protocol-compliance.md`, `CHANGELOG.md`. Each fact has one home; link to
  it instead of copying it.
- `examples/*.php` and `https_a2a_server.php` are demo servers, not production
  runtimes (`SECURITY.md`). `A2A_DEMO_AUTH_TOKEN` is a demo credential gate.
- What CI runs and what a `v*` tag triggers: `CONTRIBUTING.md`, section CI and
  releases.
