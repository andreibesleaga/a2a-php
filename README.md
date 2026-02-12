# A2A PHP SDK

A PHP implementation of the A2A (Agent-to-Agent) Protocol v0.3.0. The repository includes a fully compliant sample server, strict JSON-RPC validation logic, task management utilities, streaming support, and push notification handling.

## Quick start

```bash
# Install dependencies
composer install

# Start the reference server
php -S localhost:8081 examples/complete_a2a_server.php

# (Optional) expose an authenticated extended card
export A2A_DEMO_AUTH_TOKEN="example-secret"
```

The server exposes the JSON-RPC endpoint at `http://localhost:8081/` and publishes the agent card at `http://localhost:8081/.well-known/agent-card.json`.

## Compliance status

| Category   | Result |
| ---------- | ------ |
| Mandatory  | 25 / 25 tests passing |
| Capability | 14 / 14 tests passing |
| Quality    | 12 / 12 tests passing |
| Features   | 15 / 15 tests passing |

Results are produced with the official [A2A Test Compatibility Kit](../a2a-tck) using `python3 run_tck.py --category all`.

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
# Start the server in one terminal
php -S localhost:8081 examples/complete_a2a_server.php

# Run the TCK from the sibling repository
cd ../a2a-tck
python3 run_tck.py --sut-url http://localhost:8081 --category all

# Run the PHPUnit suite from the project root
cd ../a2a-php
composer test
```

The TCK run exercises JSON-RPC validation, streaming behaviour, push notification management, and task-state transitions. PHPUnit covers unit and integration scenarios for the PHP components.

## Documentation

Additional documentation lives in the `docs/` directory:

- `docs/README.md` – high-level overview and architecture notes.
- `docs/api-reference.md` – detailed method and data model reference.
- `docs/protocol-compliance.md` – TCK summary and behavioural guarantees.

For HTTPS deployment details, see `A2A_HTTPS_IMPLEMENTATION.md` and `HTTPS_SUMMARY.md`.

## Contributing

1. Fork the repository and create a feature branch.
2. Install dependencies with `composer install`.
3. Add unit tests or TCK scenarios covering the change.
4. Run `composer test` and (optionally) `python3 ../a2a-tck/run_tck.py`.
5. Open a pull request describing the change and validation steps.

## License

Distributed under the Apache License 2.0. See `LICENSE` for the full text.
