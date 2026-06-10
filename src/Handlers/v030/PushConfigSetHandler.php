<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\A2AErrorCodes;
use A2A\Models\PushNotificationConfig;
use A2A\Notifications\PushNotifier;
use A2A\PushNotificationManager;
use A2A\TaskManager;
use A2A\Utils\JsonRpc;

/**
 * Handles the "tasks/pushNotificationConfig/set" method.
 */
class PushConfigSetHandler implements RequestHandlerInterface
{
    private TaskManager $taskManager;
    private PushNotificationManager $pushNotificationManager;
    private PushNotifier $pushNotifier;

    public function __construct(
        TaskManager $taskManager,
        PushNotificationManager $pushNotificationManager,
        PushNotifier $pushNotifier
    ) {
        $this->taskManager = $taskManager;
        $this->pushNotificationManager = $pushNotificationManager;
        $this->pushNotifier = $pushNotifier;
    }

    public function handle(array $params, mixed $requestId): array
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

        if (!$this->pushNotifier->isWebhookUrlAllowed($config->getUrl())) {
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
}
