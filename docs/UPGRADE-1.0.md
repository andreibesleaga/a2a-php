# Upgrade path: A2A Protocol v0.3.0 → v1.0

> **Status: planning document — docs only.** Nothing in this document is
> implemented yet. The v0.3.0 implementation remains the supported,
> TCK-verified surface of this SDK. This document defines how v1.0 support
> will be added **without breaking any v0.3.0 consumer**.

A2A v1.0 was released in January 2026 under the Linux Foundation's
Agent2Agent Protocol Project. Sources:

- Spec (latest): https://a2a-protocol.org/latest/specification/
- What's new in v1.0: https://a2a-protocol.org/latest/whats-new-v1/
- Spec repo tags: `v1.0.0`, `v1.0.1` at https://github.com/a2aproject/A2A
- TCK 1.x: https://github.com/a2aproject/a2a-tck (tag `1.0.0.alpha1`+,
  CLI changed from `--sut-url` to `--sut-host`)

## 1. What changes in the protocol (v0.3.0 → v1.0)

### 1.1 Breaking changes

| Area | v0.3.0 | v1.0 |
|---|---|---|
| Parts | `TextPart`/`FilePart`/`DataPart` with `kind` discriminator | Single unified `Part`; discriminate by present member (`text`, `url`, `raw`, `data`); `mimeType` → `mediaType`; `filename` added |
| Stream events | `kind` field + `final` boolean | Named wrapper members (`taskStatusUpdate`, `taskArtifactUpdate`); `final` removed — stream closure signals completion |
| Enums | lowercase/kebab (`submitted`, `input-required`, `user`) | ProtoJSON `SCREAMING_SNAKE_CASE` with prefixes (`TASK_STATE_SUBMITTED`, `TASK_STATE_INPUT_REQUIRED`, `ROLE_USER`) |
| Agent Card | top-level `protocolVersion`, `url`, `preferredTransport`, `additionalInterfaces`, `supportsAuthenticatedExtendedCard` | `supportedInterfaces[]` (each with `url`, `protocolBinding`, `protocolVersion`); extended-card flag moves to `capabilities.extendedAgentCard` |
| Errors | JSON-RPC error objects / RFC 9457 for REST | `google.rpc.Status` + mandatory `google.rpc.ErrorInfo` (`reason` UPPER_SNAKE_CASE, domain `a2a-protocol.org`) |
| Operations | `message/send`, `message/stream`, `tasks/get`, `tasks/cancel`, `tasks/resubscribe`, `agent/getAuthenticatedExtendedCard`, `tasks/pushNotificationConfig/*` | `SendMessage`, `SendStreamingMessage`, `GetTask`, `CancelTask`, `SubscribeToTask`, `GetExtendedAgentCard`, `CreateTaskPushNotificationConfig` / `Get…` / `List…` / `Delete…`; **new** `ListTasks` with cursor pagination |
| IDs | compound resource names | separate `task_id` / `config_id` request fields |
| OAuth | implicit + password flows allowed | both removed; `DeviceCodeOAuthFlow` (RFC 8628) added; `pkceRequired` on auth-code flow |

### 1.2 New required behaviors

- **Signed Agent Cards**: `signatures[]` of JWS (RFC 7515) over the
  RFC 8785 (JCS) canonical form of the card.
- **Multi-tenancy**: `tenant` field on requests and `AgentInterface`.
- **Task visibility**: `GetTask`/`ListTasks` MUST only return tasks the
  authenticated caller may see.
- **Timestamps**: `createdAt` / `lastModified` (ISO 8601, ms precision) on
  tasks; `configId` / `createdAt` on push configs.
- **`returnImmediately`** on `SendMessage` (sync vs async execution).
- **Push payloads** become StreamResponse-shaped (v0.3 sends a plain Task).

## 2. SDK strategy: parallel version namespaces (per ADR 0003)

v1.0 support is **additive**: a new namespace family beside the v0.3.0
one. No v0.3.0 class, method, JSON shape, or env-var default changes.

```
src/
├── A2AProtocol_v030.php      # unchanged, frozen surface
├── Handlers/v030/            # unchanged
├── Models/v030/              # unchanged
├── A2AProtocolV100.php       # NEW: v1.0 entry point (PascalCase from day one)
├── Handlers/v100/            # NEW: SendMessage, GetTask, ListTasks, ...
└── Models/v100/              # NEW: unified Part, AgentCard with
                              #      supportedInterfaces, google.rpc errors
```

Key implementation notes for the future work:

1. **One server, both versions.** v1.0's `supportedInterfaces[]` is
   designed for this: a single agent advertises a v0.3.0 JSON-RPC
   interface and a v1.0 interface side by side, enabling a long
   transition window for clients.
2. **Model translation layer.** `Models\v100\Task::fromV030(...)` style
   converters localize the enum/part/discriminator changes in one place
   (state mapping such as `working` → `TASK_STATE_WORKING`, parts
   `kind: text` → member `text`).
3. **Card signing.** JWS over JCS canonical JSON; suggested deps:
   `web-token/jwt-framework` (JWS) and an RFC 8785 canonicalizer. Sign at
   publish time; verify in `A2AClient` when fetching remote cards.
4. **Push notifications.** v1.0 payloads wrap the task in a
   StreamResponse; `PushNotifier` gains a v1.0 serializer while the v0.3
   plain-Task payload stays untouched for the v0.3 path.
5. **Storage.** Add `createdAt`/`lastModified` columns to stored tasks and
   `configId`/`createdAt` to stored push configs (additive fields, ignored
   by v0.3 serializers).
6. **gRPC binding.** v1.0 is protobuf-first; implementing the gRPC binding
   becomes meaningful here (the current `GrpcClient` stub is replaced by
   generated clients from the official `a2a.proto`).

## 3. Phased delivery plan

| Phase | Scope | Gate |
|---|---|---|
| 1 | `Models/v100` (unified Part, enums, AgentCard, google.rpc error types) + unit tests | PHPUnit green; v0.3.0 suite untouched |
| 2 | `Handlers/v100` + `A2AProtocolV100` for the JSON-RPC binding; ListTasks with cursor pagination; returnImmediately | TCK `1.0.0.alpha1`+ mandatory category passes |
| 3 | Agent Card signing/verification (JWS + JCS); multi-tenancy plumbing; task visibility scoping | TCK 1.x capability category passes |
| 4 | v1.0 push payloads; timestamps; dual-interface reference server (`examples/dual_version_server.php`) | Full TCK 1.x passes **and** pinned 0.3.0.beta5 TCK still passes against the v0.3 interface |
| 5 | Optional gRPC binding from `a2a.proto` | TCK transport-equivalence category |

CI gains a second TCK job pinned to a 1.x tag; the 0.3.0 job stays —
**both must stay green** (the user-stated requirement: 100% compatibility
with all official test kits for every implemented version).

## 4. What v0.3.0 consumers must do

Nothing. The v0.3.0 surface (`A2AProtocol_v030`, `Models\v030`, JSON-RPC
method names, env vars, payload shapes) is frozen. Migration to v1.0 is
opt-in by constructing the new `A2AProtocolV100` entry point.

## 5. Migration checklist for client developers (when v1.0 lands here)

Priority order from the official migration guidance:

1. **Critical**: part parsing (`"text" in part` instead of
   `part.kind === "text"`); stream-event parsing; Agent Card discovery via
   `supportedInterfaces[]`.
2. **High**: cursor pagination for `ListTasks`; enum value updates;
   `returnImmediately` handling.
3. **Medium**: verify card signatures; check `extensions[]`; handle
   `google.rpc.ErrorInfo` reasons; new OAuth flows.
4. **Low**: consume task timestamps; tenant scoping if multi-tenant.
