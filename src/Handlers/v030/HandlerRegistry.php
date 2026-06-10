<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

/**
 * Maps A2A v0.3.0 JSON-RPC method names to their request handlers.
 */
class HandlerRegistry
{
    /** @var array<string, RequestHandlerInterface> */
    private array $handlers = [];

    public function register(string $method, RequestHandlerInterface $handler): void
    {
        $this->handlers[$method] = $handler;
    }

    public function get(string $method): ?RequestHandlerInterface
    {
        return $this->handlers[$method] ?? null;
    }

    public function has(string $method): bool
    {
        return isset($this->handlers[$method]);
    }

    /** @return string[] */
    public function methods(): array
    {
        return array_keys($this->handlers);
    }
}
