<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Events\EventBusManager;
use A2A\Exceptions\InvalidRequestException;
use A2A\Interfaces\AgentExecutor;
use A2A\Models\Task as StreamTask;
use A2A\Models\TaskStatusUpdateEvent;
use A2A\Models\v030\Message;
use A2A\Models\v030\Task;
use A2A\Streaming\StreamingServer;
use A2A\TaskManager;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Handles the "message/stream" method: registers the task, then delegates
 * to the StreamingServer which emits Server-Sent Events produced by the
 * agent executor. Task state is synchronized back into the TaskManager as
 * stream events are published.
 */
class MessageStreamHandler implements RequestHandlerInterface
{
    private TaskManager $taskManager;
    private EventBusManager $eventBusManager;
    private AgentExecutor $agentExecutor;
    private StreamingServer $streamingServer;
    private LoggerInterface $logger;

    public function __construct(
        TaskManager $taskManager,
        EventBusManager $eventBusManager,
        AgentExecutor $agentExecutor,
        StreamingServer $streamingServer,
        LoggerInterface $logger
    ) {
        $this->taskManager = $taskManager;
        $this->eventBusManager = $eventBusManager;
        $this->agentExecutor = $agentExecutor;
        $this->streamingServer = $streamingServer;
        $this->logger = $logger;
    }

    public function handle(array $params, mixed $requestId): array
    {
        if (!isset($params['message'])) {
            throw new InvalidRequestException('Missing message parameter');
        }

        $message = Message::fromArray($params['message']);

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

        $params['message'] = $message->toArray();
        $rawRequest = [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => 'message/stream',
            'params' => $params
        ];

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
}
