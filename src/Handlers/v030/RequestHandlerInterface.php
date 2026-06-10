<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

/**
 * A handler for a single A2A v0.3.0 JSON-RPC method.
 *
 * Implementations either return a complete JSON-RPC response/error array
 * or throw an A2A exception, which the protocol dispatcher maps onto the
 * appropriate JSON-RPC error envelope.
 */
interface RequestHandlerInterface
{
    /**
     * @param array<string, mixed> $params Parsed JSON-RPC params
     * @param mixed $requestId JSON-RPC request id (string, int or null)
     * @return array<string, mixed> JSON-RPC response envelope
     */
    public function handle(array $params, mixed $requestId): array;
}
