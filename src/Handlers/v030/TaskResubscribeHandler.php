<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\A2AErrorCodes;
use A2A\Streaming\StreamingServer;
use A2A\TaskManager;
use A2A\Utils\JsonRpc;

/**
 * Handles the "tasks/resubscribe" method: replays the task snapshot
 * (including history) over SSE so a client can reattach to a task stream.
 */
class TaskResubscribeHandler implements RequestHandlerInterface
{
    private TaskManager $taskManager;
    private StreamingServer $streamingServer;

    public function __construct(TaskManager $taskManager, StreamingServer $streamingServer)
    {
        $this->taskManager = $taskManager;
        $this->streamingServer = $streamingServer;
    }

    public function handle(array $params, mixed $requestId): array
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
}
