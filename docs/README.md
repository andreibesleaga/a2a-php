# A2A PHP Documentation

## Overview

A2A PHP is a complete implementation of the A2A Protocol v0.3.0. It provides a JSON-RPC transport, task lifecycle management, streaming through Server-Sent Events, and push notification configuration APIs. The project ships with a reference server (`examples/complete_a2a_server.php`) that passes every category of the A2A Test Compatibility Kit (TCK).

## Core capabilities

- **Protocol coverage**: Implements every JSON-RPC method defined by the v0.3.0 specification, including message send/stream, task retrieval, cancellation, resubscribe, and push notification management.
- **Strict validation**: All requests are checked for JSON-RPC 2.0 compliance (version, identifiers, and parameter containers). Malformed requests are rejected with precise error codes.
- **Task lifecycle**: Tasks persist history, artifacts, and metadata through the shared storage layer while exposing idempotent cancellation semantics.
- **Streaming**: The streaming server publishes JSON-RPC envelopes via SSE for both live message processing and reconnection snapshots.
- **Push notifications**: The push notification manager stores webhook configurations with list, get, set, and delete operations backed by persistent storage. On task state changes the server delivers the task snapshot to the configured webhook with `X-A2A-Notification-Token` and `Authorization` headers; the optional `A2A_WEBHOOK_ALLOWLIST` env var restricts allowed webhook hosts (SSRF guard).
- **Authenticated extended card**: Optional extended agent card data becomes available when a request includes a token that matches `A2A_DEMO_AUTH_TOKEN`.

## Architecture overview

```text
A2A PHP Architecture
├── A2AServer           # HTTP entry point handling JSON-RPC requests
├── A2AProtocol_v030    # Validates JSON-RPC and dispatches via HandlerRegistry
├── Handlers/v030/      # One request handler per JSON-RPC method + HandlerRegistry
├── TaskManager         # Creates, updates, and cancels tasks with persistence
├── PushNotificationMgr # Persists webhook configurations in shared storage
├── Notifications/      # PushNotifier: webhook delivery + allowlist enforcement
├── StreamingServer     # Emits SSE events for live processing and resubscribe
├── Storage/            # File-backed cache built on Illuminate\Cache
├── Models/             # AgentCard, Message, Task, TaskStatus, Parts, Artifacts
└── Utils/              # JsonRpc validator, HTTP client helpers, logging glue
```

### Event flow

1. **Inbound request** – JSON-RPC body is validated and routed to the appropriate handler.
2. **Task coordination** – Task metadata, history, and artifacts are updated via `TaskManager` and stored through `Storage`.
3. **Handlers and execution** – Registered message handlers (or the default executor) generate results, statuses, and artifacts.
4. **Streaming** – Events are forwarded to `StreamingServer` when using `message/stream` or `tasks/resubscribe`.
5. **Responses** – JSON-RPC responses or SSE events are returned with consistent error handling.

## Compliance summary

| Category   | Status  |
| ---------- | ------- |
| Mandatory  | All tests passing |
| Capability | All tests passing |
| Quality    | All tests passing |
| Features   | All tests passing |

Compliance is verified with the official TCK pinned at `0.3.0.beta5`
(`python3 ../a2a-tck/run_tck.py --sut-url http://localhost:8081 --category all`);
detailed counts live in `protocol-compliance.md` and the pin procedure in
`tck-upgrade.md`. CI re-runs the TCK on every pull request.

## Key behaviours guaranteed

- **JSON-RPC errors**: Invalid message payloads, unsupported part kinds, or malformed request envelopes are rejected with codes `-32600` or `-32602`.
- **Resubscribe snapshots**: `tasks/resubscribe` streams every stored history entry before emitting the current task snapshot.
- **Push config listing**: `tasks/pushNotificationConfig/list` returns an array of `{ taskId, pushNotificationConfig }` objects and supports optional filtering by task.
- **Task cancellation**: The first cancellation returns the task details; repeated requests report `TASK_NOT_CANCELABLE` to meet TCK expectations.
- **Authenticated card**: When the extended card feature is enabled, requests without credentials receive an authentication error and a `WWW-Authenticate` header.

## Running the reference server

```bash
php -S localhost:8081 examples/complete_a2a_server.php
```

Endpoints:

- `POST /` – JSON-RPC 2.0 endpoint for all protocol methods.
- `GET /.well-known/agent-card.json` – Public agent card document.
- `GET /info` – Diagnostic endpoint used by examples.

Set `A2A_DEMO_AUTH_TOKEN` to enable authenticated extended card responses.

## Related documents

- `api-reference.md` – Detailed method signatures, request/response examples, and error codes.
- `protocol-compliance.md` – Summary of the behaviour that satisfies each TCK category.
- `A2A_HTTPS_IMPLEMENTATION.md` – HTTPS/TLS deployment details.
- `HTTPS_SUMMARY.md` – High-level HTTPS migration notes.
