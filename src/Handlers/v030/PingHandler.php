<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Utils\JsonRpc;

/**
 * Handles the "ping" health-check method.
 */
class PingHandler implements RequestHandlerInterface
{
    public function handle(array $params, mixed $requestId): array
    {
        return (new JsonRpc())->createResponse($requestId, ['status' => 'pong']);
    }
}
