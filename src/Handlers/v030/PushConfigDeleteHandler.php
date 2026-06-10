<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\A2AErrorCodes;
use A2A\PushNotificationManager;
use A2A\Utils\JsonRpc;

/**
 * Handles the "tasks/pushNotificationConfig/delete" method.
 */
class PushConfigDeleteHandler implements RequestHandlerInterface
{
    private PushNotificationManager $pushNotificationManager;

    public function __construct(PushNotificationManager $pushNotificationManager)
    {
        $this->pushNotificationManager = $pushNotificationManager;
    }

    public function handle(array $params, mixed $requestId): array
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
}
