<?php

declare(strict_types=1);

namespace A2A;

use A2A\Events\EventBusManager;
use A2A\Execution\DefaultAgentExecutor;
use A2A\Handlers\v030\AgentCardHandler;
use A2A\Handlers\v030\AuthenticatedExtendedCardHandler;
use A2A\Handlers\v030\HandlerRegistry;
use A2A\Handlers\v030\MessageSendHandler;
use A2A\Handlers\v030\MessageStreamHandler;
use A2A\Handlers\v030\PingHandler;
use A2A\Handlers\v030\PushConfigDeleteHandler;
use A2A\Handlers\v030\PushConfigGetHandler;
use A2A\Handlers\v030\PushConfigListHandler;
use A2A\Handlers\v030\PushConfigSetHandler;
use A2A\Handlers\v030\TaskCancelHandler;
use A2A\Handlers\v030\TaskGetHandler;
use A2A\Handlers\v030\TaskResubscribeHandler;
use A2A\Handlers\v030\TaskSendHandler;
use A2A\Interfaces\AgentExecutor;
use A2A\Interfaces\MessageHandlerInterface;
use A2A\Models\TaskState;
use A2A\Models\TaskStatus;
use A2A\Models\v030\AgentCard;
use A2A\Models\v030\Message;
use A2A\Models\v030\Task;
use A2A\Notifications\PushNotifier;
use A2A\Streaming\StreamingServer;
use A2A\Utils\HttpClient;
use A2A\Utils\JsonRpc;
use A2A\Exceptions\A2AErrorCodes;
use A2A\Exceptions\A2AException;
use A2A\Exceptions\InvalidRequestException;
use Ramsey\Uuid\Uuid;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * A2A Protocol v0.3.0 entry point.
 *
 * JSON-RPC requests are validated here and dispatched to one handler class
 * per protocol method (see src/Handlers/v030). The public surface of this
 * class is the library's stable v0.3.0 contract.
 */
class A2AProtocol_v030
{
    private HttpClient $httpClient;
    private LoggerInterface $logger;
    private string $agentId;
    private AgentCard $agentCard;
    /** @var MessageHandlerInterface[] */
    private array $messageHandlers = [];
    private TaskManager $taskManager;
    private PushNotificationManager $pushNotificationManager;
    private EventBusManager $eventBusManager;
    private AgentExecutor $agentExecutor;
    private StreamingServer $streamingServer;
    private HandlerRegistry $handlerRegistry;

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
        $this->handlerRegistry = $this->buildHandlerRegistry();
    }

    private function buildHandlerRegistry(): HandlerRegistry
    {
        $pushNotifier = new PushNotifier(
            $this->pushNotificationManager,
            $this->httpClient,
            $this->logger
        );

        // Bound late so handlers observe message handlers registered after
        // construction, and subclasses overriding processMessage() keep
        // their behavior.
        $processMessage = fn (Message $message, string $fromAgent): array
            => $this->processMessage($message, $fromAgent);

        $registry = new HandlerRegistry();
        $registry->register('message/stream', new MessageStreamHandler(
            $this->taskManager,
            $this->eventBusManager,
            $this->agentExecutor,
            $this->streamingServer,
            $this->logger
        ));
        $registry->register('message/send', new MessageSendHandler(
            $this->taskManager,
            $processMessage,
            $pushNotifier,
            $this->logger
        ));
        $registry->register('tasks/send', new TaskSendHandler(
            $this->taskManager,
            $processMessage,
            $pushNotifier,
            $this->logger
        ));
        $registry->register('tasks/get', new TaskGetHandler($this->taskManager));
        $registry->register('tasks/cancel', new TaskCancelHandler($this->taskManager, $pushNotifier));
        $registry->register('tasks/resubscribe', new TaskResubscribeHandler(
            $this->taskManager,
            $this->streamingServer
        ));
        $registry->register('tasks/pushNotificationConfig/set', new PushConfigSetHandler(
            $this->taskManager,
            $this->pushNotificationManager,
            $pushNotifier
        ));
        $registry->register('tasks/pushNotificationConfig/get', new PushConfigGetHandler(
            $this->taskManager,
            $this->pushNotificationManager
        ));
        $registry->register('tasks/pushNotificationConfig/list', new PushConfigListHandler(
            $this->pushNotificationManager
        ));
        $registry->register('tasks/pushNotificationConfig/delete', new PushConfigDeleteHandler(
            $this->pushNotificationManager
        ));
        $registry->register('get_agent_card', new AgentCardHandler($this->agentCard));
        $registry->register(
            'agent/getAuthenticatedExtendedCard',
            new AuthenticatedExtendedCardHandler($this->agentCard)
        );
        $registry->register('ping', new PingHandler());

        return $registry;
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

        $method = $parsedRequest['method'];

        try {
            $handler = $this->handlerRegistry->get($method);

            if ($handler === null) {
                $this->logger->warning('Unknown method requested', ['method' => $method]);
                return $jsonRpc->createError(
                    $parsedRequest['id'],
                    'Unknown method: ' . $method,
                    A2AErrorCodes::METHOD_NOT_FOUND
                );
            }

            return $handler->handle($parsedRequest['params'], $parsedRequest['id']);
        } catch (InvalidRequestException $e) {
            $this->logger->error('Invalid request parameters', [
                'error' => $e->getMessage(),
                'method' => $method
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
                'method' => $method
            ]);

            return $jsonRpc->createError(
                $parsedRequest['id'],
                'Internal server error: ' . $e->getMessage(),
                A2AErrorCodes::INTERNAL_ERROR
            );
        }
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

    public function getTaskManager(): TaskManager
    {
        return $this->taskManager;
    }
}
