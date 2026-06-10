# ADR 0001: JSON-RPC 2.0 over HTTP as the primary transport

- Status: accepted
- Date: 2026-06-10 (recorded retroactively)

## Context

The A2A Protocol v0.3.0 specification defines three transport bindings:
JSON-RPC 2.0 over HTTP, gRPC, and HTTP+JSON/REST. An implementation must
support at least one. PHP's deployment reality is dominated by
request/response HTTP runtimes (PHP-FPM, built-in server, FrankenPHP);
gRPC requires a PECL extension that most shared PHP hosts do not ship.

## Decision

Implement JSON-RPC 2.0 over HTTP POST as the primary (and TCK-verified)
transport. Keep a `GrpcClient` stub that fails fast with a clear message
unless `ext-grpc` is present, and document gRPC as a suggested extension
rather than a hard dependency.

## Consequences

- The reference server runs anywhere PHP runs, including `php -S`.
- The official TCK is run in `jsonrpc` transport mode; transport
  equivalence tests are skipped (single transport).
- A future gRPC binding needs protobuf message definitions and would be
  delivered as an optional package (see docs/UPGRADE-1.0.md — v1.0 makes
  multi-binding a first-class concept).
