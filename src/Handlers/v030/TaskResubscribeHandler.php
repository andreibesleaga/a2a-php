<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\A2AErrorCodes;
use A2A\Streaming\StreamingServer;
use A2A\TaskManager;

/**
 * Handles the "tasks/resubscribe" method: replays the task snapshot
 * (including history) over SSE so a client can reattach to a task stream.
 * Like "message/stream", it returns an empty array once the stream has been
 * written, so the transport sends nothing after the SSE close event.
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
        $taskId = $params['id'] ?? null;

        if (!$taskId) {
            $this->streamingServer->streamResubscribeError(
                (string) ($requestId ?? ''),
                A2AErrorCodes::INVALID_PARAMS,
                'Task ID is required for resubscription'
            );

            return [];
        }

        $task = $this->taskManager->getTask($taskId);
        if (!$task) {
            $this->streamingServer->streamResubscribeError(
                (string) ($requestId ?? ''),
                A2AErrorCodes::TASK_NOT_FOUND,
                'Task not found',
                $taskId
            );

            return [];
        }

        $this->streamingServer->streamResubscribeSnapshot(
            (string) ($requestId ?? ''),
            $taskId,
            $task->toArray()
        );

        return [];
    }
}
