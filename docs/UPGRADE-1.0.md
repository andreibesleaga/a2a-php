# Upgrade path: A2A Protocol v0.3.0 → v1.0 (latest stable: v1.0.1)

> **Status: planning document — docs only.** Nothing in this document is
> implemented yet. The v0.3.0 implementation remains the supported,
> TCK-verified surface of this SDK. This document defines how v1.0 support
> will be added **without breaking any v0.3.0 consumer**, how the PHP SDK
> will mirror the official SDKs in other languages, and how the package
> will be published both as a plain Composer library and as a
> first-class Laravel package.

## 0. Upstream state of the ecosystem (verified June 2026)

| Artifact | Latest stable | Notes |
|---|---|---|
| A2A specification | **v1.0.1** (`a2aproject/A2A` tags: `v1.0.0`, `v1.0.1`) | v1.0 announced January 2026 under the Linux Foundation; <https://a2a-protocol.org/latest/specification/> |
| TCK | `0.3.0.beta5` (v0.3 era, pinned by this repo) and **`1.0.0.alpha1`** (v1.0 era) | 1.x CLI changed: `./run_tck.py --sut-host <url>` (was `--sut-url`), plus `--transport` and `--level must/should/may` filters |
| a2a-python (reference SDK) | **v1.1.0** | Implements spec 1.0 with **compatibility mode for 0.3**; proto-first types (ProtoJSON); see migration guide `docs/migrations/v1_0/README.md` in that repo |
| a2a-js | v0.3.x stable; **1.0 alpha on `@a2a-js/sdk@next`** | Express integration + gRPC optional peer deps |
| a2a-java | **1.0.0.CR1** | Candidate release for 1.0 |

Sources: [spec](https://a2a-protocol.org/latest/specification/) ·
[what's new in v1.0](https://a2a-protocol.org/latest/whats-new-v1/) ·
[announcement](https://a2a-protocol.org/latest/announcing-1.0/) ·
[a2a-python](https://github.com/a2aproject/a2a-python) ·
[a2a-js](https://github.com/a2aproject/a2a-js) ·
[a2a-tck](https://github.com/a2aproject/a2a-tck).

**Target for this SDK:** implement **spec v1.0.1** with a **v0.3
compatibility mode**, exactly like a2a-python v1.1.0 — both protocol
versions served from one codebase, verified by **both** TCKs.

## 1. What changes in the protocol (v0.3.0 → v1.0.x)

### 1.1 Breaking wire-level changes

| Area | v0.3.0 | v1.0.x |
|---|---|---|
| Parts | `TextPart`/`FilePart`/`DataPart` with `kind` discriminator; files nested as `FileWithBytes`/`FileWithUri` | Single unified `Part`; discriminate by present member (`text`, `url`, `raw`, `data`); `mimeType` → `mediaType`; `filename` on the part itself; no base64 wrapper semantics in proto form |
| Stream events | `kind` field + `final` boolean | Named wrapper members in a `StreamResponse` (`task`, `message`, `statusUpdate`, `artifactUpdate`); `final` removed — stream closure signals completion |
| Enums | lowercase/kebab (`submitted`, `input-required`, `user`) | ProtoJSON `SCREAMING_SNAKE_CASE` with type prefixes (`TASK_STATE_SUBMITTED`, `TASK_STATE_INPUT_REQUIRED`, `ROLE_USER`) plus `*_UNSPECIFIED` zero-variants |
| Agent Card | top-level `protocolVersion`, `url`, `preferredTransport`, `additionalInterfaces`, `supportsAuthenticatedExtendedCard` | `supportedInterfaces[]` of `AgentInterface{url, protocolBinding: JSONRPC|HTTP+JSON|GRPC, protocolVersion}`; extended-card flag becomes `capabilities.extendedAgentCard`; `examples` move from card to each `AgentSkill`; per-skill `inputModes`/`outputModes` override card-level defaults |
| Errors | JSON-RPC error objects / RFC 9457 for REST | `google.rpc.Status` + mandatory `google.rpc.ErrorInfo` (`reason` in UPPER_SNAKE_CASE, domain `a2a-protocol.org`) |
| Operations | `message/send`, `message/stream`, `tasks/get`, `tasks/cancel`, `tasks/resubscribe`, `agent/getAuthenticatedExtendedCard`, `tasks/pushNotificationConfig/*` | `SendMessage`, `SendStreamingMessage`, `GetTask`, **`ListTasks` (new, cursor pagination)**, `CancelTask`, `SubscribeToTask`, `GetExtendedAgentCard`, `CreateTaskPushNotificationConfig` / `GetTaskPushNotificationConfig` / `ListTaskPushNotificationConfigs` / `DeleteTaskPushNotificationConfig` |
| IDs | compound resource names | separate `taskId` / `configId` request fields |
| Push payloads | plain v0.3 `Task` object | `StreamResponse`-shaped payload; configs gain `configId` + `createdAt` |
| OAuth | implicit + password flows allowed | both removed; `DeviceCodeOAuthFlow` (RFC 8628) added; `pkceRequired` on the authorization-code flow |
| HTTP+JSON paths | `/v1/message:send` style | `/v1/` prefix removed |

### 1.2 New required behaviors

- **Signed Agent Cards**: `signatures[]` of JWS (RFC 7515) over the
  RFC 8785 (JCS) canonical card form.
- **Multi-tenancy**: `tenant` field on requests and `AgentInterface`.
- **Task visibility**: `GetTask`/`ListTasks` MUST return only tasks the
  authenticated caller may see.
- **Timestamps**: `createdAt`/`lastModified` (ISO 8601, ms precision) on
  tasks.
- **`returnImmediately`** on `SendMessage` (sync vs async execution).
- **AgentExecutor streaming contract** (enforced as a hard error in the
  official SDKs — see §2.2).

## 2. Functional parity with the official SDKs

The v1.0 PHP implementation must not just speak the wire format — it must
expose the **same developer-facing architecture** as a2a-python v1.1.0 and
a2a-js, so examples and mental models transfer across languages.

### 2.1 Component map (official SDK concept → PHP)

| Official SDK concept (python / js) | PHP equivalent (new, `A2A\V1` namespace family) |
|---|---|
| `AgentExecutor` (`execute(context, event_queue)`, `cancel`) | `A2A\V1\Server\AgentExecutorInterface` — `execute(RequestContext $ctx, EventQueueInterface $queue): void`, `cancelTask()` |
| `EventQueue` (single `enqueue_event`, `tap()`) | `A2A\V1\Server\EventQueueInterface` — `enqueue(Task|Message|TaskStatusUpdateEvent|TaskArtifactUpdateEvent $event): void`, `tap(): iterable` |
| `DefaultRequestHandler(agent_card, task_store, executor)` | `A2A\V1\Server\DefaultRequestHandler(AgentCard $card, TaskStoreInterface $store, AgentExecutorInterface $executor)` |
| `TaskStore` / `InMemoryTaskStore` | `A2A\V1\Server\TaskStoreInterface` + `InMemoryTaskStore`, `CacheTaskStore` (PSR-16 / Illuminate cache — reuses the existing `A2A\Storage` drivers) |
| Route factories: `create_agent_card_routes`, `create_jsonrpc_routes(..., enable_v0_3_compat=True)`, `create_rest_routes` | Framework-agnostic **PSR-15** handlers: `AgentCardRequestHandler`, `JsonRpcTransportHandler`, `RestTransportHandler` — each a `Psr\Http\Server\RequestHandlerInterface`, with an `enableV03Compat: bool` flag mirroring python's compat mode |
| `create_client()` / `ClientFactory` with transport discovery + interceptors | `A2A\V1\Client\ClientFactory` — builds a client from an agent URL or card, selects transport from `supportedInterfaces[]` (JSON-RPC first; HTTP+JSON; gRPC when `ext-grpc` present), supports `CallInterceptorInterface` (auth headers, logging) |
| `StreamResponse` iteration (`HasField`-style member check) | `A2A\V1\Models\StreamResponse` value object with `getTask()`, `getMessage()`, `getStatusUpdate()`, `getArtifactUpdate()` (exactly one non-null) |
| `a2a.helpers` (`new_text_message`, `new_task`, `get_message_text`, …) | `A2A\V1\Helpers` static factory class with the same function set and names (`newTextMessage()`, `newTask()`, `getMessageText()`, …) |
| Proto-first types | PHP value objects generated/validated against the official `a2a.proto` + JSON Schema from the spec repo, serialized as ProtoJSON (camelCase, SCREAMING_SNAKE enums). Optional native gRPC uses classes generated by `protoc` from the pinned `a2a.proto` |

### 2.2 AgentExecutor streaming contract (must match exactly)

The official SDKs enforce two mutually exclusive patterns; the PHP
`DefaultRequestHandler` must enforce the same and raise
`InvalidAgentResponseException` (already defined in this SDK) on
violation:

- **Pattern A — message-only stream**: enqueue exactly one `Message`,
  then stop.
- **Pattern B — task lifecycle stream**: enqueue a `Task` first, then
  zero or more `TaskStatusUpdateEvent`/`TaskArtifactUpdateEvent` until a
  terminal state.
- **Hard errors**: mixing `Message` with task events; multiple
  `Message`s; task events before the initial `Task`; events after a
  terminal state.

### 2.3 v0.3 compatibility mode (like a2a-python)

One server, both protocol versions:

- The agent card advertises both interfaces:
  `supportedInterfaces: [{protocolBinding: JSONRPC, protocolVersion: "1.0", url}, {protocolBinding: JSONRPC, protocolVersion: "0.3", url}]`.
- The JSON-RPC transport handler accepts both method families: v1.0
  operation names natively, and (when `enableV03Compat` is on) the
  v0.3 `message/send`-style methods routed through translation
  converters (`A2A\V1\Compat\V030MessageTranslator`,
  state mapping `working` ↔ `TASK_STATE_WORKING`, part shape
  conversion, plain-Task ↔ StreamResponse push payloads).
- The existing `A2AProtocol_v030` entry point stays frozen and delegates
  unchanged — current consumers see zero difference.

### 2.4 Test parity (all official tests, both versions)

CI gains a second TCK job; **both must stay green**:

| Gate | Kit | Command |
|---|---|---|
| v0.3 surface (existing) | `a2a-tck @ 0.3.0.beta5` | `./run_tck.py --sut-url <url> --category all` |
| v1.0 surface (new) | `a2a-tck @ 1.0.0.alpha1` (bump as 1.x tags stabilize, per `docs/tck-upgrade.md`) | `./run_tck.py --sut-host <url> --level must` then `should`/`may`, `--transport jsonrpc` (+ `grpc`, `http_json` when implemented) |

Beyond the TCK, port the official SDKs' unit-level contract tests:
executor pattern-violation tests (the four hard errors of §2.2),
StreamResponse single-member invariants, card-signature verification
vectors (JWS over JCS), cursor-pagination edge cases for `ListTasks`, and
the v0.3↔v1.0 translation round-trip (golden fixtures generated from
a2a-python v1.1.0 output so PHP serialization is byte-compatible with the
reference SDK).

## 3. Packaging: Composer library + first-class Laravel package

### 3.1 One package, two consumption modes

Keep a **single Packagist package** — `andreibesleaga/a2a-php` — that is
framework-agnostic at its core and ships an optional Laravel layer, the
same pattern used by widely adopted libs (e.g. `spatie/*`). No separate
bridge repo to keep in sync.

```
src/
├── ...                     # existing v0.3.0 code (frozen)
├── V1/                     # spec v1.0.x implementation (§2)
│   ├── Models/  Server/  Client/  Compat/  Helpers.php
└── Laravel/                # optional integration (loaded only inside Laravel)
    ├── A2AServiceProvider.php
    ├── Facades/A2A.php
    ├── Http/Controllers/A2AController.php   # thin adapters over PSR-15 handlers
    ├── Console/                              # artisan commands
    └── config/a2a.php                        # publishable config
```

Composer wiring:

```json
{
  "require": {
    "php": "^8.2",
    "psr/http-server-handler": "^1.0",
    "psr/http-factory": "^1.0"
  },
  "suggest": {
    "laravel/framework": "Enables the A2A\\Laravel integration (^11 || ^12)",
    "web-token/jwt-library": "Agent Card signing/verification (JWS, RFC 7515)",
    "ext-grpc": "Native gRPC transport binding"
  },
  "extra": {
    "laravel": {
      "providers": ["A2A\\Laravel\\A2AServiceProvider"],
      "aliases": { "A2A": "A2A\\Laravel\\Facades\\A2A" }
    }
  }
}
```

Notes:
- The core gains **no hard Laravel dependency**: today's
  `illuminate/cache|filesystem|container` requirements move behind the
  `CacheTaskStore` adapter so plain-PHP users pull only PSR interfaces
  (`composer require andreibesleaga/a2a-php` works in any framework via
  the PSR-15 handlers). This is a `require` → `suggest` move executed in
  the same major release as the v1.0 namespace so it lands as one
  documented upgrade.
- **Auto-discovery** via `extra.laravel.providers` means Laravel users do
  nothing beyond `composer require` — identical UX to first-party
  packages (opt-out supported via `dont-discover`).
- SemVer 2.0: the v1.0-capable SDK releases as the package's next major
  version; v0.3.0-only releases continue on the current major for
  security fixes per `SECURITY.md`.

### 3.2 Laravel developer experience (target)

```bash
composer require andreibesleaga/a2a-php
php artisan vendor:publish --tag=a2a-config   # publishes config/a2a.php
```

```php
// config/a2a.php (published defaults)
return [
    'card' => [
        'name' => env('A2A_AGENT_NAME', config('app.name')),
        'description' => env('A2A_AGENT_DESCRIPTION', ''),
        'version' => '1.0.0',
        'signing_key' => env('A2A_CARD_SIGNING_KEY'),      // JWS, optional
    ],
    'route' => [
        'prefix' => env('A2A_ROUTE_PREFIX', 'a2a'),        // POST /a2a (JSON-RPC)
        'middleware' => ['api'],
        'well_known' => true,    // GET /.well-known/agent-card.json
        'v03_compat' => true,    // serve legacy v0.3 methods too
    ],
    'executor' => App\Agents\MyAgentExecutor::class,
    'task_store' => 'cache',     // cache | database | memory
    'push' => [
        'queue' => env('A2A_PUSH_QUEUE', 'default'),       // webhook delivery via Laravel queues
        'allowlist' => env('A2A_WEBHOOK_ALLOWLIST'),
    ],
    'tenant_resolver' => null,   // callable/class for multi-tenancy (v1.0 'tenant' field)
];
```

What `A2AServiceProvider` wires (each a thin adapter over the
framework-agnostic core, so behavior is identical outside Laravel):

- **Routes**: registers `POST /{prefix}` (JSON-RPC transport),
  `GET /.well-known/agent-card.json`, and optional REST-binding routes —
  honoring the configured middleware stack (auth, throttle, tenant).
- **Container bindings**: `AgentExecutorInterface`, `TaskStoreInterface`
  (backed by Laravel's cache/database per config), `ClientFactory`,
  `EventQueueInterface`.
- **Facade** `A2A` for client-side calls:
  `A2A::client('https://other-agent.example')->sendMessage(...)`.
- **Queued push delivery**: webhook notifications dispatch a
  `DeliverPushNotification` job (retries/backoff via Horizon-compatible
  queues) instead of blocking the request — with the synchronous PSR-18
  path kept for non-Laravel users.
- **Events**: dispatches `TaskStatusChanged`, `TaskArtifactAdded`,
  `PushDeliveryFailed` Laravel events so apps can listen natively.
- **Artisan commands**: `a2a:card` (print/validate/sign the agent card),
  `a2a:serve` (dev server wrapper), `a2a:tck` (run the pinned TCKs
  against a local URL).
- **Testing**: package tested against Laravel 11 and 12 via
  `orchestra/testbench` in a dedicated CI job; the core suites never
  boot Laravel.

## 4. Phased delivery plan (revised)

| Phase | Scope | Gate |
|---|---|---|
| 1 | `V1\Models` from pinned `a2a.proto` + spec JSON Schema (unified Part, enums, AgentCard/AgentInterface, `google.rpc` errors, StreamResponse); ProtoJSON serialization fixtures generated from a2a-python v1.1.0 | PHPUnit green incl. byte-compat fixtures; v0.3.0 suite untouched |
| 2 | `V1\Server`: EventQueue, AgentExecutor contract (§2.2 enforcement), DefaultRequestHandler, TaskStore (memory/cache), PSR-15 JSON-RPC transport; `ListTasks` + cursor pagination; `returnImmediately` | TCK `1.0.0.alpha1` `--level must` passes (jsonrpc transport) |
| 3 | v0.3 compat mode (`Compat\` translators, dual-interface card); `V1\Client` + ClientFactory with interceptors | **Both** TCKs fully green against the dual-version reference server |
| 4 | Card signing/verification (JWS + RFC 8785), multi-tenancy, task-visibility scoping, v1.0 push payloads via queue-able delivery | TCK 1.x `should` level; signature test vectors pass |
| 5 | `Laravel/` layer (provider, config, facade, jobs, events, artisan) + testbench matrix (Laravel 11/12); core deps slimmed to PSR (illuminate → suggest) | Testbench CI green; `composer require` works in a fresh Laravel app and a plain PHP app |
| 6 | Optional REST (HTTP+JSON) binding; optional gRPC binding from `a2a.proto` via `protoc` (behind `ext-grpc`) | TCK `--transport http_json` / `grpc` + transport-equivalence category |

Release: tag as the package's next major on Packagist with the SBOM
workflow already in place; announce v0.3-compat guarantees in the
CHANGELOG; keep the `0.3.0.beta5` TCK job permanently (the user-stated
requirement: 100% compatibility with **all** official test kits for every
implemented protocol version).

## 5. What v0.3.0 consumers must do

Nothing. `A2AProtocol_v030`, `Models\v030`, JSON-RPC method names, env
vars, and payload shapes stay frozen. v1.0 is opt-in via the new
`A2A\V1\…` entry points (or, in Laravel, via the published config), and
the dual-interface agent card lets remote v0.3 clients keep working
against an upgraded server.

## 6. Migration checklist for client developers (when v1.0 lands here)

Priority order from the official migration guidance:

1. **Critical**: part parsing (member presence instead of `kind`);
   stream-event parsing via `StreamResponse`; Agent Card discovery via
   `supportedInterfaces[]`.
2. **High**: cursor pagination for `ListTasks`; SCREAMING_SNAKE enum
   values; `returnImmediately` handling.
3. **Medium**: verify card signatures; check `extensions[]`; handle
   `google.rpc.ErrorInfo` reasons; new OAuth flows (device code, PKCE).
4. **Low**: consume task timestamps; tenant scoping if multi-tenant.
