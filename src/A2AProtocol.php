<?php

declare(strict_types=1);

namespace A2A;

use A2A\Models\AgentCard;
use A2A\Models\Task;
use A2A\Models\TaskState;
use A2A\Models\Message;
use A2A\Models\Part;
use A2A\Utils\JsonRpc;
use A2A\Utils\HttpClient;
use A2A\Exceptions\A2AException;
use A2A\Exceptions\InvalidRequestException;
use A2A\Exceptions\TaskNotFoundException;
use A2A\Exceptions\A2AErrorCodes;
use A2A\Interfaces\MessageHandlerInterface;
use A2A\TaskManager;
use Ramsey\Uuid\Uuid;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class A2AProtocol
{
    private HttpClient $httpClient;
    private LoggerInterface $logger;
    private string $agentId;
    private AgentCard $agentCard;
    private array $messageHandlers = [];
    private TaskManager $taskManager;

    public function __construct(
        AgentCard $agentCard,
        ?HttpClient $httpClient = null,
        ?LoggerInterface $logger = null,
        ?TaskManager $taskManager = null
    ) {
        $this->agentCard = $agentCard;
        $this->agentId = $agentCard->getName();
        $this->httpClient = $httpClient ?? new HttpClient();
        $this->logger = $logger ?? new NullLogger();
        $this->taskManager = $taskManager ?? new TaskManager();
    }

    public function getAgentCard(): AgentCard
    {
        return $this->agentCard;
    }

    public function createTask(string $description, array $context = []): Task
    {
        $taskId = Uuid::uuid4()->toString();
        $contextId = $context['contextId'] ?? Uuid::uuid4()->toString();

        $status = new \A2A\Models\TaskStatus(\A2A\Models\TaskState::SUBMITTED);

        $metadata = $context;
        $metadata['description'] = $description;

        $task = new Task($taskId, $contextId, $status, [], [], $metadata);

        $this->logger->info(
            'Task created',
            [
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
            'message/send',
            [
            'from' => $this->agentId,
            'message' => $message->toArray()
            ]
        );

        try {
            $response = $this->httpClient->post($recipientUrl, $request);
            $this->logger->info(
                'Message sent successfully',
                [
                'recipient' => $recipientUrl,
                'message_id' => $message->getMessageId()
                ]
            );
            return $response;
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to send message',
                [
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

            switch ($parsedRequest['method']) {
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
                    throw new InvalidRequestException('Unknown method: ' . $parsedRequest['method']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Request handling failed', ['error' => $e->getMessage()]);
            return $jsonRpc->createError($request['id'] ?? null, $e->getMessage());
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

        // NOTE: In a real implementation, this would check authentication
        // and potentially return a different, more detailed AgentCard.
        // For this implementation, we return the same card.
        return $jsonRpc->createResponse($requestId, $this->agentCard->toArray());
    }

    private function handleMessage(array $params, $requestId): array
    {
        $message = Message::fromArray($params['message']);
        $fromAgent = $params['from'] ?? 'unknown';

        $this->logger->info(
            'Message received',
            [
            'from' => $fromAgent,
            'message_id' => $message->getMessageId(),
            'content_type' => 'text'
            ]
        );

        $result = $this->processMessage($message, $fromAgent);

        $jsonRpc = new JsonRpc();
        return $jsonRpc->createResponse($requestId, $result);
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
                        'Message handler failed',
                        [
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

        return $jsonRpc->createResponse($requestId, $result['result']);
    }

    private function handleResubscribeTask(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? null;

        if (!$taskId) {
            return $jsonRpc->createError($requestId, 'Missing task ID', A2AErrorCodes::INVALID_PARAMS);
        }

        // Resubscribe logic would go here
        return $jsonRpc->createResponse($requestId, ['status' => 'resubscribed', 'taskId' => $taskId]);
    }

    private function handleSetPushConfig(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['taskId'] ?? null;
        $config = $params['pushNotificationConfig'] ?? null;

        if (!$taskId || !$config) {
            return $jsonRpc->createError($requestId, 'Missing required parameters', A2AErrorCodes::INVALID_PARAMS);
        }

        return $jsonRpc->createResponse($requestId, ['taskId' => $taskId, 'pushNotificationConfig' => $config]);
    }

    private function handleGetPushConfig(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? null;

        if (!$taskId) {
            return $jsonRpc->createError($requestId, 'Missing task ID', A2AErrorCodes::INVALID_PARAMS);
        }

        return $jsonRpc->createResponse($requestId, ['taskId' => $taskId, 'pushNotificationConfig' => null]);
    }

    private function handleListPushConfig(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        return $jsonRpc->createResponse($requestId, ['configs' => []]);
    }
    private function handleDeletePushConfig(array $params, $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? null;

        if (!$taskId) {
            throw new InvalidRequestException('Task ID is required');
        }

        // For now, return empty result as delete isn't implemented
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
            $task->setStatus(TaskState::WORKING);

            // Process the message
            $result = $this->processMessage($messageObj, 'tasks/send');

            // Update task with artifacts if any
            if (isset($result['artifacts'])) {
                foreach ($result['artifacts'] as $artifactData) {
                    $task->addArtifact($artifactData);
                }
            }

            // Set final status
            $task->setStatus(TaskState::COMPLETED);

            return $jsonRpc->createResponse($requestId, $task->toArray());
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to send task',
                [
                'task_id' => $taskId,
                'error' => $e->getMessage()
                ]
            );

            // Update task to failed status if it exists
            if (isset($task)) {
                $task->setStatus(TaskState::FAILED);
            }

            throw $e;
        }
    }

    public function getTaskManager(): TaskManager
    {
        return $this->taskManager;
    }
}
