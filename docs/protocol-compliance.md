# A2A Protocol v0.3.0 Compliance Report

This document summarises how the PHP reference implementation satisfies the A2A Test Compatibility Kit (TCK). All results were generated with `python3 ../a2a-tck/run_tck.py --sut-url http://localhost:8081 --category all` using the bundled `examples/complete_a2a_server.php` implementation and the TCK pinned at **`0.3.0.beta5`** (latest v0.3.0-era release; see `docs/tck-upgrade.md`).

## Results overview

| Category   | Status            | Notes |
| ---------- | ----------------- | ----- |
| Mandatory  | 32 passed, 0 failed, 1 skipped | Covers transport, agent discovery, task basics, and error responses. |
| Capability | 39 passed, 0 failed, 1 xfail, 38 skipped | Exercises streaming, push notifications (including real webhook delivery), contextual messaging, and authenticated cards. Skips are undeclared optional capabilities. Capability honesty: "excellent". |
| Transport equivalence | 16 skipped | Single JSON-RPC transport (see ADR 0001). |
| Quality    | 14 passed, 0 failed | Validates robustness, error semantics, and idempotency. |
| Features   | 15 passed, 0 failed, 1 skipped | Confirms optional behaviours such as task history replay and artifact handling. |

> Historical note: earlier revisions of this document reported 66/66
> against a 2025 TCK snapshot. The kit has since grown — notably with
> push-notification *delivery* tests (TCK PR #178) — and the
> implementation now passes the enlarged pinned suite in full, including
> actual webhook delivery with `X-A2A-Notification-Token` and
> `Authorization` headers and a plain v0.3 Task payload.

## Mandatory suite highlights

- **JSON-RPC validation**: `Utils\JsonRpc::parseRequest` enforces version, id, and method checks. Invalid requests trigger `-32600` or `-32602` errors, satisfying malformed transport cases.
- **Message handling**: `message/send` accepts well-formed message objects, normalises legacy part kinds, and returns task snapshots containing metadata and history.
- **Task retrieval**: `tasks/get` honours the optional `historyLength` parameter and returns `TASK_NOT_FOUND (-32001)` when a task is missing.
- **Error mapping**: Protocol-level failures map to the correct A2A error codes via `A2AErrorCodes`.

## Capability suite highlights

- **Streaming reconnection**: `tasks/resubscribe` replays stored history events through `StreamingServer::streamResubscribeSnapshot`, then emits the current task status. Errors stream through `streamResubscribeError` with JSON-RPC envelopes.
- **Push notifications**: `PushNotificationManager` persists configurations in `Storage`, and the list/get/set/delete handlers return deterministic payloads expected by the TCK.
- **Authenticated extended card**: Requests to `agent/getAuthenticatedExtendedCard` enforce the `A2A_DEMO_AUTH_TOKEN` credential, returning authentication errors with `WWW-Authenticate` headers when absent or invalid.
- **Message context**: Message payloads automatically populate `taskId` and `contextId` when missing, ensuring downstream methods receive consistent identifiers.

## Quality suite highlights

- **Task cancellation semantics**: `TaskManager::cancelTask` returns the task once and then surfaces `TASK_NOT_CANCELABLE (-32002)` on repeated calls, matching idempotency requirements.
- **Legacy payload support**: `Part::fromArray` recognises `type`, `text`, and `content` aliases, preventing failures when older clients interact with the server.
- **Structured error logging**: All handlers capture validation failures and internal exceptions, allowing the TCK to confirm correct error surfaces without leaking stack traces.
- **Storage resilience**: File-backed storage in `/tmp/a2a_storage` is cleared between tests and reliably returns consistent task snapshots for history-verification checks.

## Features suite highlights

- **History replay**: SSE streams deliver message history followed by the latest task snapshot, proving compliance with reconnection scenarios.
- **Artifacts**: Handler outputs merged into task metadata and artifacts persist across `tasks/get` calls, satisfying artifact inspection tests.
- **Push notification listing**: The list endpoint returns an array of `{ taskId, pushNotificationConfig }` objects, filtered by task when requested.
- **Extended metadata**: Handler responses enrich task metadata with additional keys, which are preserved and surfaced to clients.

## Verification checklist

1. Start the server: `php -S localhost:8081 examples/complete_a2a_server.php`.
2. Run the TCK: `python3 ../a2a-tck/run_tck.py --sut-url http://localhost:8081 --category all`.
3. Inspect `tck/reports/latest/` for the HTML summary if desired.
4. Review `a2a_server.log` (created by the reference server) for structured handling logs.

## Known limitations

- Batch JSON-RPC requests remain unsupported; the TCK does not exercise them.
- Streaming relies on the built-in PHP server for demonstration; production deployments should terminate SSE via a dedicated web server.

With these constraints noted, the implementation satisfies every compliance category required by the A2A Protocol v0.3.0 specification.
