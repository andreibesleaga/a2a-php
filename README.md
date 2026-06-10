# A2A PHP SDK

A PHP implementation of the A2A (Agent-to-Agent) Protocol (v0.3.0). The repository includes the unofficial library implementation and a fully compliant sample server, strict JSON-RPC validation logic, task management utilities, streaming support, and push notification handling.

## Quick start

```bash
# Install dependencies
composer install

# Start the reference server
php -S localhost:8081 examples/complete_a2a_server.php

# (Optional) expose an authenticated extended card
export A2A_DEMO_AUTH_TOKEN="example-secret"

# (Optional, recommended in production) restrict push webhook hosts
export A2A_WEBHOOK_ALLOWLIST="hooks.example.com,callbacks.example.com"
```

The server exposes the JSON-RPC endpoint at `http://localhost:8081/` and publishes the agent card at `http://localhost:8081/.well-known/agent-card.json`.

## Compliance status

Verified against the official [A2A Test Compatibility Kit](https://github.com/a2aproject/a2a-tck)
pinned at **`0.3.0.beta5`** (the latest v0.3.0-era TCK release) — **every
category passes**:

| Category | Result |
| -------- | ------ |
| Mandatory | 32 passed, 0 failed (1 skipped: not applicable) |
| Capability | 39 passed, 0 failed — capability honesty "excellent" |
| Transport equivalence | skipped (single JSON-RPC transport) |
| Quality | 14 passed, 0 failed |
| Features | 15 passed, 0 failed (1 skipped) |

The TCK is re-run on every pull request by CI (`.github/workflows/ci.yml`);
the pin and upgrade procedure are documented in `docs/tck-upgrade.md`.

## Configuration

| Env var | Default | Effect |
| ------- | ------- | ------ |
| `A2A_DEMO_AUTH_TOKEN` | unset | When set, `agent/getAuthenticatedExtendedCard` requires a matching `Authorization: Bearer` or `X-API-Key` credential. Demo-grade gate only. |
| `A2A_WEBHOOK_ALLOWLIST` | unset (allow all) | Comma-separated hostnames allowed as push notification webhook targets. Enforced when configs are stored **and** at delivery time (SSRF guard). |
| `A2A_MODE` / `A2A_FORCE_HTTPS` | unset | Used only by the demo `https_a2a_server.php` (see `A2A_HTTPS_IMPLEMENTATION.md`). |

## Implementation highlights

- Strict JSON-RPC 2.0 validation with precise error codes for malformed requests, invalid identifiers, or incorrect parameter payloads.
- Task lifecycle support with history, metadata, artifact persistence, and idempotent cancellation semantics.
- Server-Sent Events streaming, including the `tasks/resubscribe` snapshot feed that replays history before emitting the current task status.
- Push notification configuration management with consistent list, get, set, and delete behaviour backed by the shared storage layer.
- Authenticated extended agent card endpoint gated by the `A2A_DEMO_AUTH_TOKEN` environment variable for demonstration purposes.

## Reference server methods

| Method | Description | Notes |
| ------ | ----------- | ----- |
| `get_agent_card` | Returns the public agent card. | JSON-RPC response. |
| `agent/getAuthenticatedExtendedCard` | Returns the extended card when `Authorization: Bearer` or `X-API-Key` matches `A2A_DEMO_AUTH_TOKEN`. | JSON-RPC response. |
| `message/send` | Processes an inbound message and returns the task snapshot. | Validates message parts and metadata. |
| `message/stream` | Starts an SSE session for live task updates while processing a streamed message. | Emits JSON-RPC envelopes over SSE. |
| `tasks/send` | Accepts an explicit task and message payload, ensuring reuse of existing task IDs. | Sets status to working and finalises with handler result. |
| `tasks/get` | Retrieves the latest task state, history, and artifacts. | Supports the optional `historyLength` parameter. |
| `tasks/cancel` | Cancels a running task once and reports an error on repeated cancellation attempts. | Uses `TaskManager::cancelTask`. |
| `tasks/resubscribe` | Emits task history and current status over SSE for reconnecting clients. | Utilises stored history snapshots. |
| `tasks/pushNotificationConfig/set` | Persists a push configuration for a task. | Stores webhook data in shared storage. |
| `tasks/pushNotificationConfig/get` | Returns the stored configuration for a task. | Null when absent. |
| `tasks/pushNotificationConfig/list` | Lists all stored configurations, optionally filtered by `taskId`. | Returns an array of summary objects. |
| `tasks/pushNotificationConfig/delete` | Removes the configuration for a task. | Returns `null` on success. |
| `ping` | Health-check method. | Returns `{ "status": "pong" }`. |

Refer to `docs/api-reference.md` for complete request and response schemas.

## Working with Server-Sent Events

The reference server streams events from the same JSON-RPC endpoint when the `message/stream` or `tasks/resubscribe` methods are invoked. Responses are encoded as JSON-RPC payloads and delivered as SSE events with IDs set to the task or message identifiers. The `StreamingServer` guarantees that a resubscribe replay includes every stored message history entry before the terminal task status update.

## Testing

```bash
# Full PHPUnit suite (unit + integration + e2e; the e2e suite boots the
# reference server and a webhook receiver on ephemeral ports by itself)
composer test

# Static analysis and coding standard
composer analyse        # PHPStan level 8 (baseline-ratcheted)
vendor/bin/phpcs -n     # PSR-12

# Official TCK, pinned version (see docs/tck-upgrade.md)
git clone --branch 0.3.0.beta5 https://github.com/a2aproject/a2a-tck.git ../a2a-tck
cd ../a2a-tck && pip install -e . && cd -
php -S localhost:8081 examples/complete_a2a_server.php &
python3 ../a2a-tck/run_tck.py --sut-url http://localhost:8081 --category all
```

The TCK run exercises JSON-RPC validation, streaming behaviour, push notification management and **delivery**, and task-state transitions. PHPUnit covers golden-path, edge-case, error-path and end-to-end scenarios for the PHP components.

## Manual review guide

For reviewing a change or release by hand:

| Step | What to check | Where | Expected outcome |
| ---- | ------------- | ----- | ---------------- |
| Config | `composer validate --strict`; env vars in the Configuration table above | `composer.json`, this README | Valid manifest; defaults preserve previous behavior |
| Run | `php -S localhost:8081 examples/complete_a2a_server.php`, then `curl -s -X POST http://localhost:8081/ -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","method":"ping","id":1}'` | `examples/` | `{"jsonrpc":"2.0","id":1,"result":{"status":"pong"}}` |
| Examples | Run each script under `examples/` with `php examples/<name>.php` (servers run under `php -S`) | `examples/` | No PHP warnings/errors in output |
| Tests | `composer test`, `composer analyse`, `vendor/bin/phpcs -n` | `tests/`, `phpstan.neon.dist`, `phpcs.xml.dist` | All green, no new PHPStan baseline entries |
| Compliance | TCK procedure above | `docs/tck-upgrade.md` | All categories pass at the pinned TCK ref |
| Logs | Inspect `a2a_server.log` produced by the reference server | repo root | Structured log lines, no stack traces leaked to clients |

## Documentation

Additional documentation lives in the `docs/` directory:

- `docs/README.md` – high-level overview and architecture notes.
- `docs/api-reference.md` – detailed method and data model reference.
- `docs/protocol-compliance.md` – TCK summary and behavioural guarantees.
- `docs/tck-upgrade.md` – TCK version pin and upgrade procedure.
- `docs/UPGRADE-1.0.md` – upgrade path to A2A protocol v1.0 (planning doc).
- `docs/adr/` – architecture decision records.

For HTTPS deployment details, see `A2A_HTTPS_IMPLEMENTATION.md` and `HTTPS_SUMMARY.md`.
Security policy: `SECURITY.md`. Release history: `CHANGELOG.md`.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). In short: fork, branch,
`composer install`, add golden-path/edge/error tests for the change, keep
`composer test`, `composer analyse`, `vendor/bin/phpcs -n` and the pinned
TCK green, then open a PR describing the validation steps.

## License

Distributed under the Apache License 2.0. See `LICENSE` for the full text.
