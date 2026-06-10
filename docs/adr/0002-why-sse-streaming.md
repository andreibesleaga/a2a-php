# ADR 0002: Server-Sent Events for streaming

- Status: accepted
- Date: 2026-06-10 (recorded retroactively)

## Context

A2A v0.3.0 `message/stream` and `tasks/resubscribe` require the server to
push incremental task/message events to the client. Candidates in PHP:
WebSockets (needs a long-running event loop — ReactPHP/Amp/Swoole),
long-polling, or Server-Sent Events (SSE).

## Decision

Use SSE (`text/event-stream`), as required by the A2A spec for the
JSON-RPC transport's streaming methods: each event carries a complete
JSON-RPC response envelope. `SSEStreamer` handles headers/flushing;
`StreamingServer` adapts executor events to SSE frames.

## Consequences

- Works in plain PHP request lifecycles without an async runtime.
- One streaming response per request process: PHP's built-in server is
  single-threaded, so the demo server handles one stream at a time —
  acceptable for the reference/demo scope, documented in SECURITY.md.
- True concurrent streaming (many long-lived connections) needs an async
  runtime (ReactPHP/Amp/Swoole/FrankenPHP worker mode); that is a v2
  architecture concern (see audit Future/V4).
