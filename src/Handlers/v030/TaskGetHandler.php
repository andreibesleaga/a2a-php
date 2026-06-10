<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\A2AErrorCodes;
use A2A\TaskManager;
use A2A\Utils\JsonRpc;

/**
 * Handles the "tasks/get" method.
 */
class TaskGetHandler implements RequestHandlerInterface
{
    private TaskManager $taskManager;

    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function handle(array $params, mixed $requestId): array
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
}
