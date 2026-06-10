# A2A PHP API Reference

This document describes the JSON-RPC API exposed by the reference A2A PHP server (`examples/complete_a2a_server.php`). All methods follow the A2A Protocol v0.3.0 specification and are available through an HTTP POST request to the server root (`/`).

## Transport overview

- **Protocol**: JSON-RPC 2.0 over HTTP POST.
- **Endpoint**: `http://<host>/` (for development the default is `http://localhost:8081/`).
- **Content type**: `application/json`.
- **Batch requests**: Not currently supported; send one request per HTTP call.
- **Authentication**: Only the authenticated extended card method requires credentials.

### Sample request

```json
{
  "jsonrpc": "2.0",
  "id": "req-123",
  "method": "message/send",
  "params": {
    "from": "client-agent",
    "message": {
      "kind": "message",
      "messageId": "msg-1",
      "role": "user",
      "parts": [
        { "kind": "text", "text": "Process the sample payload." }
      ]
    }
  }
}
```

### Sample success response

```json
{
  "jsonrpc": "2.0",
  "id": "req-123",
  "result": {
    "kind": "task",
    "id": "task-42",
    "contextId": "ctx-1",
    "status": {
      "state": "working",
      "timestamp": "2024-07-18T17:42:11Z"
    },
    "metadata": {
      "message": "Task is still in progress",
      "interactionCount": 1
    }
  }
}
```

### Sample error response

```json
{
  "jsonrpc": "2.0",
  "id": "req-123",
  "error": {
    "code": -32602,
    "message": "Invalid or missing message payload"
  }
}
```

## Data models

### AgentCard

The agent card follows the A2A specification and is returned by `get_agent_card` and `agent/getAuthenticatedExtendedCard`.

Key fields:

| Field | Type | Notes |
| ----- | ---- | ----- |
| `name` | string | Unique agent name. |
| `description` | string | Human-readable description. |
| `url` | string | Base URL for the agent. |
| `version` | string | Agent version string. |
| `protocolVersion` | string | Always `0.3.0` for this implementation. |
| `capabilities` | object | Includes `canReceiveMessages`, `canSendMessages`, `canManageTasks`, `supportsStreaming`, `supportsPushNotifications`. |
| `defaultInputModes` | array | Content types accepted by default. |
| `defaultOutputModes` | array | Content types produced by default. |
| `skills` | array | Describes available skills. |
| `supportsAuthenticatedExtendedCard` | bool | Indicates whether the authenticated endpoint is available. |

### Message

Messages must contain at least one part.

| Field | Type | Notes |
| ----- | ---- | ----- |
| `kind` | string | Must be `message`. |
| `messageId` | string | Unique identifier; generated if omitted in handlers. |
| `role` | string | Usually `user` or `agent`. |
| `parts` | array | Each entry contains a `kind` and payload. |
| `taskId` | string (optional) | Associates the message with a task. Generated automatically when absent. |
| `contextId` | string (optional) | Logical context identifier, generated when absent. |
| `referenceTaskIds` | array (optional) | Related task identifiers. |
| `metadata` | object (optional) | Additional user-defined metadata. |

Supported part kinds:

- `text`: `{ "kind": "text", "text": "...", "metadata": { ... } }`
- `file`: `{ "kind": "file", "file": { "uri": "https://..." } }` or `{ "kind": "file", "file": { "bytes": "<base64>" } }`
- `data`: `{ "kind": "data", "data": { ... } }`

Legacy payloads with `type` instead of `kind`, or `content` instead of `text`, are normalised where possible.

### Task

Tasks are returned by `message/send`, `tasks/send`, `tasks/get`, and SSE resubscribe snapshots.

| Field | Type | Notes |
| ----- | ---- | ----- |
| `kind` | string | Always `task`. |
| `id` | string | Task identifier. |
| `contextId` | string | Context identifier associated with the task. |
| `status` | object | Contains `state` (submitted, working, input-required, completed, failed, canceled, rejected, auth-required) and `timestamp`. |
| `history` | array | Contains message objects; returned on demand or during resubscribe. |
| `artifacts` | array | Optional; contains artifact descriptors created by handlers. |
| `metadata` | object | Optional; includes handler-provided metadata. |

### PushNotificationConfig

Configuration persisted through the `tasks/pushNotificationConfig/*` methods.

| Field | Type | Notes |
| ----- | ---- | ----- |
| `url` | string | Destination webhook URL. |
| `id` | string (optional) | User-provided identifier. |
| `token` | string (optional) | Token returned in callbacks. |
| `authentication` | object (optional) | Contains `schemes` (string array) and optional `credentials`. |

## Method reference

### get_agent_card

Returns the public agent card.

#### Request (get_agent_card)

```json
{
  "jsonrpc": "2.0",
  "id": "agent-card-1",
  "method": "get_agent_card"
}
```

#### Response (get_agent_card)

`result` contains the agent card object described above.

#### Error handling (get_agent_card)

None under normal circumstances.

---

### agent/getAuthenticatedExtendedCard

Returns the same card as `get_agent_card`, but only when the caller provides credentials. It demonstrates the authenticated extended card flow.

#### Headers (agent/getAuthenticatedExtendedCard)

Provide either `Authorization: Bearer <token>` or `X-API-Key: <token>`. The token must match the `A2A_DEMO_AUTH_TOKEN` environment variable value.

#### Request (agent/getAuthenticatedExtendedCard)

```json
{
  "jsonrpc": "2.0",
  "id": "agent-card-extended",
  "method": "agent/getAuthenticatedExtendedCard"
}
```

#### Responses (agent/getAuthenticatedExtendedCard)

- Success: Same payload as `get_agent_card`.
- Missing credentials: Error with code `-32007` and message "Authentication required for authenticated extended card". The server sets `WWW-Authenticate: Bearer realm="A2A"`.
- Invalid credentials: Error with code `-32007` and message "Invalid authentication credentials". The server sets `WWW-Authenticate: Bearer realm="A2A", error="invalid_token"`.

---

### ping

Simple health check returning `{"status": "pong"}`.

#### Request (ping)

```json
{
  "jsonrpc": "2.0",
  "id": "ping-1",
  "method": "ping"
}
```

#### Response (ping)

```json
{
  "jsonrpc": "2.0",
  "id": "ping-1",
  "result": {
    "status": "pong"
  }
}
```

---

### message/send

Processes an inbound message, updates or creates the associated task, and returns the task snapshot.

#### Parameters (message/send)

| Name | Type | Required | Notes |
| ---- | ---- | -------- | ----- |
| `from` | string | Optional | Identifier for the calling agent. Defaults to `"unknown"` when omitted. |
| `message` | object | Required | Message object. Must include `parts`. |

#### Response (message/send)

`result` is the updated task. The server sets the task status to `working` while the registered handler processes the message and may change it to `completed` or `failed`.

#### Error handling (message/send)

- `-32602` when `message` is missing, not an object, or has no parts.
- `-32602` when the message structure cannot be parsed (invalid part kinds, missing role, and similar issues).
- `-32603` or more specific A2A error codes when handlers raise exceptions.

---

### message/stream

Initiates a Server-Sent Events (SSE) stream. The request body matches `message/send`, but the HTTP response remains open and emits JSON-RPC envelopes encoded as SSE events.

#### Usage (message/stream)

1. Send an HTTP POST request with `Accept: text/event-stream`.
2. Keep the connection open to observe events.
3. Events include:
   - `event: message` for message history entries.
   - `event: task-status` for task status updates.
   - `event: error` when failures occur.

#### Event stream example (message/stream)

```text
event: message
data: {"jsonrpc":"2.0","id":"stream-1","result":{"kind":"message","messageId":"msg-1",...}}

event: task-status
data: {"jsonrpc":"2.0","id":"stream-1","result":{"kind":"task","id":"task-42","status":{"state":"completed"}}}
```

#### Error handling (message/stream)

- The server emits an `error` event with code `-32602` when `message` is missing.
- Connection closes after the stream is completed or an error occurs.

---

### tasks/send

Ensures a specific task ID exists, appends the provided message to the history, runs handlers, and returns the resulting task snapshot. This mirrors `message/send` but requires a task identifier supplied by the caller.

#### Parameters (tasks/send)

| Name | Type | Required | Notes |
| ---- | ---- | -------- | ----- |
| `id` | string | Required | Task identifier to create or reuse. |
| `message` | object | Required | Message object. |
| `sessionId` | string | Optional | Recorded in metadata. |
| `pushNotification` | object | Optional | Currently logged but not required. |
| `historyLength` | integer | Optional | Currently ignored; history is always persisted. |
| `metadata` | object | Optional | Merged into task metadata. |

#### Response (tasks/send)

Same as `message/send`.

#### Error handling (tasks/send)

- `-32602` if `id` or `message` is missing.
- `-32602` if the message fails validation.

---

### tasks/get

Returns the latest task snapshot. Optionally limits the number of history entries returned.

#### Parameters (tasks/get)

| Name | Type | Required | Notes |
| ---- | ---- | -------- | ----- |
| `id` | string | Required | Task identifier. |
| `historyLength` | integer | Optional | When set, only the most recent `historyLength` messages are returned. Must be zero or positive. |

#### Response (tasks/get)

`result` is the task object. When `historyLength` is provided, the `history` array is truncated accordingly.

#### Error handling (tasks/get)

- `-32001` when the task does not exist.
- `-32602` when `id` is missing or when `historyLength` is negative.

---

### tasks/cancel

Cancels a running task by ID.

#### Parameters (tasks/cancel)

| Name | Type | Required | Notes |
| ---- | ---- | -------- | ----- |
| `id` | string | Required | Task identifier. |

#### Response (tasks/cancel)

On the first successful cancellation the server returns the task snapshot with status `canceled`.

#### Error handling (tasks/cancel)

- `-32006` (`INVALID_AGENT_RESPONSE`) when `id` is missing.
- `-32002` when the task is already completed or has been cancelled previously.
- `-32001` when the task does not exist.

---

### tasks/resubscribe

Replays stored task history and emits the latest status over an SSE stream. Useful for clients that lost their streaming connection.

#### Parameters (tasks/resubscribe)

| Name | Type | Required | Notes |
| ---- | ---- | -------- | ----- |
| `id` | string | Required | Task identifier. |

#### Behaviour (tasks/resubscribe)

1. The server emits one SSE event per stored history entry (`event: message`).
2. A final `task-status` event is emitted with the complete task snapshot.
3. The stream closes after the snapshot is sent.

#### Error handling (tasks/resubscribe)

- If `id` is missing, the server emits an `error` event with code `-32602`.
- If the task does not exist, the server emits an `error` event with code `-32001`.

---

### tasks/pushNotificationConfig/set

Stores a push notification configuration for the given task.

Once stored, the server delivers the task snapshot (a plain v0.3 `Task`
object with `kind: "task"`) to the configured `url` via HTTP POST on every
subsequent task state change. The request carries
`X-A2A-Notification-Token: <token>` when `token` is configured and
`Authorization: <scheme> <credentials>` when `authentication` is
configured. Delivery is best-effort: failures are logged server-side and
never affect the JSON-RPC response.

#### Parameters (tasks/pushNotificationConfig/set)

| Name | Type | Required | Notes |
| ---- | ---- | -------- | ----- |
| `taskId` | string | Required | Task identifier. Accepts the alias `id`. |
| `pushNotificationConfig` | object | Required | Configuration payload described in the PushNotificationConfig section. |

#### Response (tasks/pushNotificationConfig/set)

```json
{
  "jsonrpc": "2.0",
  "id": "config-set-1",
  "result": {
    "taskId": "task-42",
    "pushNotificationConfig": {
      "url": "https://example.com/webhook",
      "token": "secret"
    }
  }
}
```

#### Error handling (tasks/pushNotificationConfig/set)

- `-32602` when parameters are missing or malformed (including a missing/empty `url`).
- `-32602` when `A2A_WEBHOOK_ALLOWLIST` is set and the webhook URL host is not in the allowlist.
- `-32603` when persistence fails.

---

### tasks/pushNotificationConfig/get

Retrieves the configuration for a task.

#### Parameters (tasks/pushNotificationConfig/get)

| Name | Type | Required | Notes |
| ---- | ---- | -------- | ----- |
| `taskId` | string | Required | Accepts alias `id`. |

#### Response (tasks/pushNotificationConfig/get)

`result` contains `taskId` and `pushNotificationConfig`. The configuration is `null` when none is stored.

#### Error handling (tasks/pushNotificationConfig/get)

- `-32602` when `taskId` is missing.
- `-32001` when the task does not exist.

---

### tasks/pushNotificationConfig/list

Lists all stored push notification configurations. When `taskId` is supplied, the list is filtered to that task.

#### Parameters (tasks/pushNotificationConfig/list)

| Name | Type | Required | Notes |
| ---- | ---- | -------- | ----- |
| `taskId` | string | Optional | Accepts alias `id`. |

#### Response (tasks/pushNotificationConfig/list)

An array of objects:

```json
[
  {
    "taskId": "task-42",
    "pushNotificationConfig": {
      "url": "https://example.com/webhook",
      "token": "secret"
    }
  }
]
```

#### Error handling (tasks/pushNotificationConfig/list)

None. When no configurations exist, an empty array is returned.

---

### tasks/pushNotificationConfig/delete

Deletes the stored configuration for the specified task.

#### Parameters (tasks/pushNotificationConfig/delete)

| Name | Type | Required | Notes |
| ---- | ---- | -------- | ----- |
| `id` | string | Required | Task identifier. |

#### Response (tasks/pushNotificationConfig/delete)

`result` is `null` on success.

#### Error handling (tasks/pushNotificationConfig/delete)

- `-32602` when `id` is missing.
- `-32001` when the configuration does not exist.

---

## Error codes

| Code | Name | Description |
| ---- | ---- | ----------- |
| `-32700` | `PARSE_ERROR` | Invalid JSON received by the server. |
| `-32600` | `INVALID_REQUEST` | JSON-RPC envelope failed validation (missing method, wrong version, invalid id). |
| `-32601` | `METHOD_NOT_FOUND` | Unknown method name. |
| `-32602` | `INVALID_PARAMS` | Request parameters failed validation. |
| `-32603` | `INTERNAL_ERROR` | Generic server error; check logs for details. |
| `-32001` | `TASK_NOT_FOUND` | Task does not exist in storage. |
| `-32002` | `TASK_NOT_CANCELABLE` | Task has already completed or was previously cancelled. |
| `-32003` | `PUSH_NOTIFICATION_NOT_SUPPORTED` | Reserved for future use when push notifications are disabled. |
| `-32004` | `UNSUPPORTED_OPERATION` | Reserved for future use. |
| `-32005` | `CONTENT_TYPE_NOT_SUPPORTED` | Returned when a message part type cannot be processed. |
| `-32006` | `INVALID_AGENT_RESPONSE` | Used when required task identifiers are missing. |
| `-32007` | `AUTHENTICATED_EXTENDED_CARD_NOT_CONFIGURED` | Authentication is required or failed for the extended card method.

All responses strictly follow the JSON-RPC structure: `{"jsonrpc":"2.0","id":<same as request>,"result":...}` on success or include an `error` object otherwise.
