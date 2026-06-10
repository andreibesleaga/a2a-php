<?php

declare(strict_types=1);

namespace A2A;

use A2A\Events\EventBusManager;
use A2A\Execution\DefaultAgentExecutor;
use A2A\Interfaces\AgentExecutor;
use A2A\Interfaces\MessageHandlerInterface;
use A2A\Models\Artifact;
use A2A\Models\PushNotificationConfig;
use A2A\Models\Task as StreamTask;
use A2A\Models\TaskState;
use A2A\Models\TaskStatus;
use A2A\Models\TaskStatusUpdateEvent;
use A2A\Models\v030\AgentCard;
use A2A\Models\v030\Message;
use A2A\Models\v030\Task;
use A2A\PushNotificationManager;
use A2A\Streaming\StreamingServer;
use A2A\TaskManager;
use A2A\Utils\HttpClient;
use A2A\Utils\JsonRpc;
use A2A\Exceptions\A2AErrorCodes;
use A2A\Exceptions\A2AException;
use A2A\Exceptions\InvalidRequestException;
use Ramsey\Uuid\Uuid;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class A2AProtocol_v030
{
    private HttpClient $httpClient;
    private LoggerInterface $logger;
    private string $agentId;
    private AgentCard $agentCard;
    private array $messageHandlers = [];
    private TaskManager $taskManager;
    private PushNotificationManager $pushNotificationManager;
    private EventBusManager $eventBusManager;
    private AgentExecutor $agentExecutor;
    private StreamingServer $streamingServer;

    public function __construct(
        AgentCard $agentCard,
        ?HttpClient $httpClient = null,
        ?LoggerInterface $logger = null,
        ?TaskManager $taskManager = null,
        ?PushNotificationManager $pushNotificationManager = null,
        ?EventBusManager $eventBusManager = null,
        ?AgentExecutor $agentExecutor = null,
        ?StreamingServer $streamingServer = null
    ) {
        $this->agentCard = $agentCard;
        $this->agentId = $agentCard->getName();
        $this->httpClient = $httpClient ?? new HttpClient();
        $this->logger = $logger ?? new NullLogger();
        $this->taskManager = $taskManager ?? new TaskManager();
        $this->pushNotificationManager = $pushNotificationManager ?? new PushNotificationManager();
        $this->eventBusManager = $eventBusManager ?? new EventBusManager();
        $this->agentExecutor = $agentExecutor ?? new DefaultAgentExecutor();
        $this->streamingServer = $streamingServer ?? new StreamingServer();
    }

    public function getAgentCard(): AgentCard
    {
        return $this->agentCard;
    }

    public function createTask(string $description, array $context = []): Task
    {
        $taskId = Uuid::uuid4()->toString();
        $contextId = $context['contextId'] ?? Uuid::uuid4()->toString();

        $status = new TaskStatus(TaskState::SUBMITTED);

        $metadata = $context;
        $metadata['description'] = $description;

        $task = new Task($taskId, $contextId, $status, [], [], $metadata);

        $this->logger->info(
            'Task created', [
            'task_id' => $taskId,
            'description' => $description
            ]
        );

        return $task;
    }

    public function sendMessage(string $recipientUrl, Message $message): array
    {
        $jsonRpc = new JsonRpc();
        $request = $jsonRpc->createRequest(
            'message/send', [
            'from' => $this->agentId,
            'message' => $message->toArray()
            ]
        );

        try {
            $response = $this->httpClient->post($recipientUrl, $request);
            $this->logger->info(
                'Message sent successfully', [
                'recipient' => $recipientUrl,
                'message_id' => $message->getMessageId()
                ]
            );
            return $response;
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to send message', [
                'recipient' => $recipientUrl,
                'error' => $e->getMessage()
                ]
            );
            throw new A2AException('Failed to send message: ' . $e->getMessage());
        }
    }

    public function handleRequest(array $request): array
    {
        $jsonRpc = new JsonRpc();

        try {
            $parsedRequest = $jsonRpc->parseRequest($request);
        } catch (InvalidRequestException $e) {
            $this->logger->error('JSON-RPC request validation failed', [
                'error' => $e->getMessage(),
                'request' => $request
            ]);

            return $jsonRpc->createError(
                $request['id'] ?? null,
                $e->getMessage(),
                A2AErrorCodes::INVALID_REQUEST
            );
        }

        try {
            switch ($parsedRequest['method']) {
            case 'message/stream':
                return $this->handleMessageStream($parsedRequest, $request);
            case 'message/send':
                return $this->handleMessage($parsedRequest['params'], $parsedRequest['id']);
            case 'tasks/send':
                return $this->handleSendTask($parsedRequest['params'], $parsedRequest['id']);
            case 'tasks/get':
                return $this->handleGetTask($parsedRequest['params'], $parsedRequest['id']);
            case 'tasks/cancel':
                return $this->handleCancelTask($parsedRequest['params'], $parsedRequest['id']);
            case 'tasks/resubscribe':
                return $this->handleResubscribeTask($parsedRequest['params'], $parsedRequest['id']);
            case 'tasks/pushNotificationConfig/set':
                return $this->handleSetPushConfig($parsedRequest['params'], $parsedRequest['id']);
            case 'tasks/pushNotificationConfig/get':
                return $this->handleGetPushConfig($parsedRequest['params'], $parsedRequest['id']);
            case 'tasks/pushNotificationConfig/list':
                return $this->handleListPushConfig($parsedRequest['params'], $parsedRequest['id']);
            case 'tasks/pushNotificationConfig/delete':
                return $this->handleDeletePushConfig($parsedRequest['params'], $parsedRequest['id']);
            case 'get_agent_card':
                return $jsonRpc->createResponse($parsedRequest['id'], $this->agentCard->toArray());
            case 'agent/getAuthenticatedExtendedCard':
                return $this->handleGetAuthenticatedExtendedCard($parsedRequest['id']);
            case 'ping':
                return $jsonRpc->createResponse($parsedRequest['id'], ['status' => 'pong']);
            default:
                $this->logger->warning('Unknown method requested', ['method' => $parsedRequest['method']]);
                return $jsonRpc->createError(
                    $parsedRequest['id'],
                    'Unknown method: ' . $parsedRequest['method'],
                    A2AErrorCodes::METHOD_NOT_FOUND
                );
            }
        } catch (InvalidRequestException $e) {
            $this->logger->error('Invalid request parameters', [
                'error' => $e->getMessage(),
                'method' => $parsedRequest['method']
            ]);

            return $jsonRpc->createError(
                $parsedRequest['id'],
                $e->getMessage(),
                A2AErrorCodes::INVALID_PARAMS
            );
        } catch (A2AException $e) {
            $code = $e->getCode() !== 0 ? $e->getCode() : A2AErrorCodes::INTERNAL_ERROR;

            return $jsonRpc->createError(
                $parsedRequest['id'],
                $e->getMessage(),
                $code
            );
        } catch (\Throwable $e) {
            $this->logger->error('Request handling failed', [
                'error' => $e->getMessage(),
                'method' => $parsedRequest['method']
            ]);

            return $jsonRpc->createError(
                $parsedRequest['id'],
                'Internal server error: ' . $e->getMessage(),
                A2AErrorCodes::INTERNAL_ERROR
            );
        }
    }

    private function handleGetAuthenticatedExtendedCard($requestId): array
    {
        $jsonRpc = new JsonRpc();

        if (!$this->agentCard->getSupportsAuthenticatedExtendedCard()) {
            return $jsonRpc->createError(
                $requestId,
                A2AErrorCodes::getErrorMessage(A2AErrorCodes::AUTHENTICATED_EXTENDED_CARD_NOT_CONFIGURED),
                A2AErrorCodes::AUTHENTICATED_EXTENDED_CARD_NOT_CONFIGURED
            );
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $hasCredentials = trim($authHeader) !== '' || trim($apiKeyHeader) !== '';

        if (!$hasCredentials) {
            if (function_exists('header')) {
                header('WWW-Authenticate: Bearer realm="A2A"');
            }

            return $jsonRpc->createError(
                $requestId,
                'Authentication required for authenticated extended card',
                A2AErrorCodes::AUTHENTICATED_EXTENDED_CARD_NOT_CONFIGURED
            );
        }

        $expectedToken = getenv('A2A_DEMO_AUTH_TOKEN');
        $providedToken = '';

        if (trim($authHeader) !== '') {
            $providedToken = trim(preg_replace('/^Bearer\s+/i', '', $authHeader));
        } elseif (trim($apiKeyHeader) !== '') {
            $providedToken = trim($apiKeyHeader);
        }

        if ($expectedToken !== false && $expectedToken !== '' && $providedToken !== $expectedToken) {
            if (function_exists('header')) {
                header('WWW-Authenticate: Bearer realm="A2A", error="invalid_token"');
            }

            return $jsonRpc->createError(
                $requestId,
                'Invalid authentication credentials',
                A2AErrorCodes::AUTHENTICATED_EXTENDED_CARD_NOT_CONFIGURED
            );
        }

        // NOTE: In a real implementation, this would return an extended card
        // with additional details based on the authenticated principal.
        return $jsonRpc->createResponse($requestId, $this->agentCard->toArray());
    }

    private function handleMessage(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();

        if (!isset($params['message']) || !is_array($params['message'])) {
            return $jsonRpc->createError(
                $requestId,
                'Invalid or missing message payload',
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        $messagePayload = $params['message'];

        if (empty($messagePayload['parts']) || !is_array($messagePayload['parts'])) {
            return $jsonRpc->createError(
                $requestId,
                'Message parts must be a non-empty array',
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        try {
            $message = Message::fromArray($messagePayload);
        } catch (\Throwable $e) {
            $this->logger->warning('Message payload validation failed', [
                'error' => $e->getMessage(),
                'payload' => $messagePayload
            ]);

            return $jsonRpc->createError(
                $requestId,
                'Invalid message structure: ' . $e->getMessage(),
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        $fromAgent = $params['from'] ?? 'unknown';

        $taskId = $message->getTaskId() ?? Uuid::uuid4()->toString();
        if ($message->getTaskId() === null) {
            $message->setTaskId($taskId);
        }

        $contextId = $message->getContextId() ?? Uuid::uuid4()->toString();
        if ($message->getContextId() === null) {
            $message->setContextId($contextId);
        }

        $task = $this->taskManager->getTask($taskId);

        if (!$task) {
            $task = $this->taskManager->createTask(
                $messagePayload['metadata']['description'] ?? 'Message processing task',
                [
                    'contextId' => $contextId,
                    'source' => 'message/send',
                    'fromAgent' => $fromAgent
                ],
                $taskId
            );
        }

        $task->addToHistory($message);
        $task->setStatus(new TaskStatus(TaskState::WORKING));
        $this->taskManager->updateTask($task);

        $handlerResult = $this->processMessage($message, $fromAgent);

        $metadata = $task->getMetadata();

        if (is_array($handlerResult)) {
            if (!empty($handlerResult['metadata']) && is_array($handlerResult['metadata'])) {
                $metadata = array_merge($metadata, $handlerResult['metadata']);
            }

            $knownKeys = ['status', 'artifacts', 'metadata'];
            $additionalMetadata = [];
            foreach ($handlerResult as $key => $value) {
                if (!in_array($key, $knownKeys, true)) {
                    $additionalMetadata[$key] = $value;
                }
            }

            if (!empty($additionalMetadata)) {
                $metadata = array_merge($metadata, $additionalMetadata);
            }
        }

        $task->setMetadata($metadata);

        if (is_array($handlerResult) && !empty($handlerResult['artifacts']) && is_array($handlerResult['artifacts'])) {
            foreach ($handlerResult['artifacts'] as $artifactData) {
                $this->attachArtifactToTask($task, $artifactData);
            }
        }

        $task->setStatus($this->resolveTaskStatus($handlerResult));
        $this->taskManager->updateTask($task);
        $this->notifyPushListeners($task);

        $this->logger->info(
            'Message processed', [
            'from' => $fromAgent,
            'message_id' => $message->getMessageId(),
            'task_id' => $task->getId(),
            'state' => $task->getStatus()->getState()->value
            ]
        );

        return $jsonRpc->createResponse($requestId, $task->toArray());
    }

    public function addMessageHandler(MessageHandlerInterface $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    protected function processMessage(Message $message, string $fromAgent): array
    {
        foreach ($this->messageHandlers as $handler) {
            if ($handler->canHandle($message)) {
                try {
                    return $handler->handle($message, $fromAgent);
                } catch (\Exception $e) {
                    $this->logger->error(
                        'Message handler failed', [
                        'handler' => get_class($handler),
                        'error' => $e->getMessage(),
                        'message_id' => $message->getMessageId()
                        ]
                    );
                }
            }
        }

        // Default response if no handler processes the message
        return [
            'status' => 'received',
            'message_id' => $message->getMessageId(),
            'timestamp' => time()
        ];
    }

    private function attachArtifactToTask(Task $task, mixed $artifactData): void
    {
        try {
            if ($artifactData instanceof Artifact) {
                $task->addArtifact($artifactData);
                return;
            }

            if (is_array($artifactData)) {
                $task->addArtifact(Artifact::fromArray($artifactData));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to attach artifact to task', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function resolveTaskStatus(mixed $handlerResult): TaskStatus
    {
        if ($handlerResult instanceof TaskStatus) {
            return $handlerResult;
        }

        $statusPayload = null;

        if (is_array($handlerResult) && array_key_exists('status', $handlerResult)) {
            $statusPayload = $handlerResult['status'];
        } elseif ($handlerResult instanceof TaskStatus) {
            return $handlerResult;
        }

        if ($statusPayload instanceof TaskStatus) {
            return $statusPayload;
        }

        $state = TaskState::COMPLETED;
        $statusMessage = null;

        if (is_array($statusPayload)) {
            if (isset($statusPayload['state']) && is_string($statusPayload['state'])) {
                $state = $this->mapStringToTaskState($statusPayload['state']);
            }

            if (isset($statusPayload['message']) && is_array($statusPayload['message'])) {
                $statusMessage = $this->createStatusMessageFromArray($statusPayload['message']);
            }
        } elseif (is_string($statusPayload)) {
            $state = $this->mapStringToTaskState($statusPayload);
        }

        return new TaskStatus($state, $statusMessage);
    }

    private function mapStringToTaskState(string $state): TaskState
    {
        $normalized = strtolower($state);

        return match ($normalized) {
            'submitted' => TaskState::SUBMITTED,
            'working', 'in-progress', 'processing' => TaskState::WORKING,
            'input-required', 'input_required', 'awaiting_input', 'awaiting-input' => TaskState::INPUT_REQUIRED,
            'canceled', 'cancelled' => TaskState::CANCELED,
            'failed', 'error' => TaskState::FAILED,
            'rejected' => TaskState::REJECTED,
            'auth-required', 'authentication_required', 'authentication-required' => TaskState::AUTH_REQUIRED,
            'received' => TaskState::SUBMITTED,
            'completed', 'done', 'success', 'processed' => TaskState::COMPLETED,
            default => TaskState::COMPLETED,
        };
    }

    private function createStatusMessageFromArray(array $data): ?Message
    {
        try {
            return Message::fromArray($data);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to hydrate status message from array', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function handleGetTask(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? null;

        if (!$taskId) {
            return $jsonRpc->createError($requestId, 'Missing task ID', A2AErrorCodes::INVALID_AGENT_RESPONSE);
        }

        $task = $this->taskManager->getTask($taskId);
        if (!$task) {
            return $jsonRpc->createError($requestId, 'Task not found', A2AErrorCodes::TASK_NOT_FOUND);
        }

        $historyLength = $params['historyLength'] ?? null;

        if ($historyLength !== null) {
            if (is_string($historyLength) && is_numeric($historyLength)) {
                $historyLength = (int) $historyLength;
            }

            if (!is_int($historyLength)) {
                return $jsonRpc->createError(
                    $requestId,
                    'historyLength must be an integer',
                    A2AErrorCodes::INVALID_PARAMS
                );
            }

            if ($historyLength < 0) {
                return $jsonRpc->createError(
                    $requestId,
                    'historyLength must be greater than or equal to zero',
                    A2AErrorCodes::INVALID_PARAMS
                );
            }
        }
        $taskArray = $task->toArray();

        if ($historyLength !== null) {
            $taskArray['history'] = array_map(
                fn($msg) => $msg->toArray(),
                $task->getHistory($historyLength)
            );
        }

        return $jsonRpc->createResponse($requestId, $taskArray);
    }

    private function handleCancelTask(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? null;

        if (!$taskId) {
            return $jsonRpc->createError($requestId, 'Missing task ID', A2AErrorCodes::INVALID_AGENT_RESPONSE);
        }

        $result = $this->taskManager->cancelTask($taskId);

        if (isset($result['error'])) {
            return $jsonRpc->createError($requestId, $result['error']['message'], $result['error']['code']);
        }

        $canceledTask = $this->taskManager->getTask($taskId);
        if ($canceledTask !== null) {
            $this->notifyPushListeners($canceledTask);
        }

        return $jsonRpc->createResponse($requestId, $result['result']);
    }

    private function handleResubscribeTask(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? null;

        if (!$taskId) {
            $this->streamingServer->streamResubscribeError(
                (string) ($requestId ?? ''),
                A2AErrorCodes::INVALID_PARAMS,
                'Task ID is required for resubscription'
            );

            return $jsonRpc->createError(
                $requestId,
                'Task ID is required for resubscription',
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        $task = $this->taskManager->getTask($taskId);
        if (!$task) {
            $this->streamingServer->streamResubscribeError(
                (string) ($requestId ?? ''),
                A2AErrorCodes::TASK_NOT_FOUND,
                'Task not found',
                $taskId
            );

            return $jsonRpc->createError(
                $requestId,
                'Task not found',
                A2AErrorCodes::TASK_NOT_FOUND
            );
        }

        $this->streamingServer->streamResubscribeSnapshot(
            (string) ($requestId ?? ''),
            $taskId,
            $task->toArray()
        );

        return $jsonRpc->createResponse(
            $requestId,
            [
                'status' => 'resubscribed',
                'taskId' => $taskId,
                'task' => $task->toArray()
            ]
        );
    }

    private function handleMessageStream(array $parsedRequest, array $rawRequest): array
    {
        if (!isset($parsedRequest['params']['message'])) {
            throw new InvalidRequestException('Missing message parameter');
        }

        $message = Message::fromArray($parsedRequest['params']['message']);

        $taskId = $message->getTaskId() ?? Uuid::uuid4()->toString();
        if ($message->getTaskId() === null) {
            $message->setTaskId($taskId);
        }

        $contextId = $message->getContextId() ?? Uuid::uuid4()->toString();
        if ($message->getContextId() === null) {
            $message->setContextId($contextId);
        }

        $task = $this->taskManager->getTask($taskId);
        if (!$task) {
            $task = $this->taskManager->createTask(
                'Streaming task',
                ['contextId' => $contextId],
                $taskId
            );
        }

        $task->addToHistory($message);
        $this->taskManager->updateTask($task);

        if (!isset($rawRequest['params']) || !is_array($rawRequest['params'])) {
            $rawRequest['params'] = [];
        }
        $rawRequest['params']['message'] = $message->toArray();

        $eventBus = $this->eventBusManager->getEventBus($taskId);

        $eventBus->subscribe(
            $taskId,
            function ($event) use ($taskId, $contextId) {
                $this->synchronizeStreamingTask($taskId, $contextId, $event);
            }
        );

        try {
            $this->streamingServer->handleStreamRequest(
                $rawRequest,
                $this->agentExecutor,
                $eventBus
            );
        } finally {
            $eventBus->unsubscribe($taskId);
            $this->eventBusManager->removeEventBus($taskId);
        }

        return [];
    }

    private function synchronizeStreamingTask(string $taskId, string $contextId, mixed $event): void
    {
        $task = $this->taskManager->getTask($taskId);

        if (!$task) {
            $task = $this->taskManager->createTask(
                'Streaming task',
                ['contextId' => $contextId],
                $taskId
            );
        }

        if ($event instanceof TaskStatusUpdateEvent) {
            $task->setStatus($event->getStatus());
            $this->taskManager->updateTask($task);
            return;
        }

        if ($event instanceof StreamTask) {
            $taskData = $event->toArray();
            $taskData['id'] = $taskData['id'] ?? $taskId;
            $taskData['contextId'] = $taskData['contextId'] ?? $contextId;

            try {
                $updatedTask = Task::fromArray($taskData);
                $this->taskManager->updateTask($updatedTask);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to synchronize streaming task', [
                    'task_id' => $taskId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Deliver the current task snapshot to the configured push notification
     * webhook, if one exists for the task (A2A v0.3.0 §9.5).
     *
     * Delivery failures are logged and never interrupt request handling.
     */
    private function notifyPushListeners(Task $task): void
    {
        $config = $this->pushNotificationManager->getConfig($task->getId());
        if ($config === null) {
            return;
        }

        $url = $config->getUrl();

        if (!$this->isWebhookUrlAllowed($url)) {
            $this->logger->warning('Push notification skipped: webhook host not in allowlist', [
                'task_id' => $task->getId(),
                'url' => $url
            ]);
            return;
        }

        $headers = [];

        if ($config->getToken() !== null && $config->getToken() !== '') {
            $headers['X-A2A-Notification-Token'] = $config->getToken();
        }

        $authentication = $config->getAuthentication();
        if ($authentication !== null) {
            $schemes = $authentication->toArray()['schemes'] ?? [];
            $credentials = $authentication->toArray()['credentials'] ?? null;
            if (!empty($schemes) && is_string($credentials) && $credentials !== '') {
                $headers['Authorization'] = $schemes[0] . ' ' . $credentials;
            }
        }

        try {
            $this->httpClient->postNotification($url, $task->toArray(), $headers);
            $this->logger->info('Push notification delivered', [
                'task_id' => $task->getId(),
                'state' => $task->getStatus()->getState()->value
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Push notification delivery failed', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Enforce the optional A2A_WEBHOOK_ALLOWLIST env var (comma-separated
     * hostnames). When unset or empty, every webhook URL is allowed, which
     * preserves the pre-allowlist behavior.
     */
    private function isWebhookUrlAllowed(string $url): bool
    {
        $allowList = getenv('A2A_WEBHOOK_ALLOWLIST');
        if ($allowList === false || trim($allowList) === '') {
            return true;
        }

        $allowedHosts = array_map('trim', explode(',', $allowList));
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && in_array($host, $allowedHosts, true);
    }

    private function handleSetPushConfig(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['taskId'] ?? $params['id'] ?? null;
        $configData = $params['pushNotificationConfig'] ?? null;

        if (!$taskId || !is_array($configData)) {
            return $jsonRpc->createError(
                $requestId,
                'Missing required parameters',
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        $task = $this->taskManager->getTask($taskId);
        if (!$task) {
            return $jsonRpc->createError(
                $requestId,
                'Task not found',
                A2AErrorCodes::TASK_NOT_FOUND
            );
        }

        try {
            $config = PushNotificationConfig::fromArray($configData);
        } catch (\Throwable $e) {
            return $jsonRpc->createError(
                $requestId,
                'Invalid push notification configuration',
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        if (!$this->isWebhookUrlAllowed($config->getUrl())) {
            return $jsonRpc->createError(
                $requestId,
                'Webhook URL host is not in the allowlist',
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        if (!$this->pushNotificationManager->setConfig($taskId, $config)) {
            return $jsonRpc->createError(
                $requestId,
                'Failed to persist push notification configuration',
                A2AErrorCodes::INTERNAL_ERROR
            );
        }

        return $jsonRpc->createResponse(
            $requestId,
            [
                'taskId' => $taskId,
                'pushNotificationConfig' => $config->toArray()
            ]
        );
    }

    private function handleGetPushConfig(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? $params['taskId'] ?? null;

        if (!$taskId) {
            return $jsonRpc->createError(
                $requestId,
                'Missing task ID',
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        $task = $this->taskManager->getTask($taskId);
        if (!$task) {
            return $jsonRpc->createError(
                $requestId,
                'Task not found',
                A2AErrorCodes::TASK_NOT_FOUND
            );
        }

        $config = $this->pushNotificationManager->getConfig($taskId);

        return $jsonRpc->createResponse(
            $requestId,
            [
                'taskId' => $taskId,
                'pushNotificationConfig' => $config ? $config->toArray() : null
            ]
        );
    }

    private function handleListPushConfig(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? $params['taskId'] ?? null;

        $configs = $this->pushNotificationManager->listConfigs($taskId);

        return $jsonRpc->createResponse(
            $requestId,
            $configs
        );
    }

    private function handleDeletePushConfig(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? null;

        if (!$taskId) {
            return $jsonRpc->createError($requestId, 'Task ID is required', A2AErrorCodes::INVALID_PARAMS);
        }

        $deleted = $this->pushNotificationManager->deleteConfig($taskId);

        if (!$deleted) {
            return $jsonRpc->createError(
                $requestId,
                'Task not found',
                A2AErrorCodes::TASK_NOT_FOUND
            );
        }

        return $jsonRpc->createResponse($requestId, null);
    }

    private function handleSendTask(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();

        // Extract task parameters
        $taskId = $params['id'] ?? null;
        $sessionId = $params['sessionId'] ?? null;
        $message = $params['message'] ?? null;
        $pushNotification = $params['pushNotification'] ?? null;
        $historyLength = $params['historyLength'] ?? null;
        $metadata = $params['metadata'] ?? [];

        if (!$taskId || !$message) {
            throw new InvalidRequestException('Task ID and message are required');
        }

        try {
            $messageObj = Message::fromArray($message);

            // Check if task already exists, if not create it
            $task = $this->taskManager->getTask($taskId);
            if (!$task) {
                // Create new task with the provided taskId
                $task = $this->taskManager->createTask(
                    'Task created via tasks/send',
                    array_merge($metadata, ['taskId' => $taskId]),
                    $taskId
                );
            }

            // Add message to task history
            $task->addToHistory($messageObj);

            // Update task status to working
            $task->setStatus(new TaskStatus(TaskState::WORKING));

            // Process the message
            $result = $this->processMessage($messageObj, 'tasks/send');

            // Update task with artifacts if any
            if (isset($result['artifacts'])) {
                foreach ($result['artifacts'] as $artifactData) {
                    $task->addArtifact($artifactData);
                }
            }

            // Set final status
            $task->setStatus(new TaskStatus(TaskState::COMPLETED));
            $this->taskManager->updateTask($task);
            $this->notifyPushListeners($task);

            return $jsonRpc->createResponse($requestId, $task->toArray());
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to send task', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
                ]
            );

            // Update task to failed status if it exists
            if (isset($task)) {
                $task->setStatus(new TaskStatus(TaskState::FAILED));
            }

            throw $e;
        }
    }

    public function getTaskManager(): TaskManager
    {
        return $this->taskManager;
    }
}