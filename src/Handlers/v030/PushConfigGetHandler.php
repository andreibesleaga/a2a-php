<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\A2AErrorCodes;
use A2A\PushNotificationManager;
use A2A\TaskManager;
use A2A\Utils\JsonRpc;

/**
 * Handles the "tasks/pushNotificationConfig/get" method.
 */
class PushConfigGetHandler implements RequestHandlerInterface
{
    private TaskManager $taskManager;
    private PushNotificationManager $pushNotificationManager;

    public function __construct(TaskManager $taskManager, PushNotificationManager $pushNotificationManager)
    {
        $this->taskManager = $taskManager;
        $this->pushNotificationManager = $pushNotificationManager;
    }

    public function handle(array $params, mixed $requestId): array
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
}
