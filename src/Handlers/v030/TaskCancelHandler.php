<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\A2AErrorCodes;
use A2A\Notifications\PushNotifier;
use A2A\TaskManager;
use A2A\Utils\JsonRpc;

/**
 * Handles the "tasks/cancel" method.
 */
class TaskCancelHandler implements RequestHandlerInterface
{
    private TaskManager $taskManager;
    private PushNotifier $pushNotifier;

    public function __construct(TaskManager $taskManager, PushNotifier $pushNotifier)
    {
        $this->taskManager = $taskManager;
        $this->pushNotifier = $pushNotifier;
    }

    public function handle(array $params, mixed $requestId): array
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
            $this->pushNotifier->notify($canceledTask);
        }

        return $jsonRpc->createResponse($requestId, $result['result']);
    }
}
