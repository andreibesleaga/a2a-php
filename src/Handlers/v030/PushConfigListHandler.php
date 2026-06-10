<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\PushNotificationManager;
use A2A\Utils\JsonRpc;

/**
 * Handles the "tasks/pushNotificationConfig/list" method.
 */
class PushConfigListHandler implements RequestHandlerInterface
{
    private PushNotificationManager $pushNotificationManager;

    public function __construct(PushNotificationManager $pushNotificationManager)
    {
        $this->pushNotificationManager = $pushNotificationManager;
    }

    public function handle(array $params, mixed $requestId): array
    {
        $jsonRpc = new JsonRpc();
        $taskId = $params['id'] ?? $params['taskId'] ?? null;

        $configs = $this->pushNotificationManager->listConfigs($taskId);

        return $jsonRpc->createResponse($requestId, $configs);
    }
}
