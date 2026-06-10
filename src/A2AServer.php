<?php

declare(strict_types=1);

namespace A2A;

use A2A\Models\v030\AgentCard;
use A2A\Interfaces\MessageHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class A2AServer
{
    private A2AProtocol_v030 $protocol;
    private LoggerInterface $logger;

    public function __construct(
        A2AProtocol_v030 $protocol,
        ?LoggerInterface $logger = null
    ) {
        $this->protocol = $protocol;
        $this->logger = $logger ?? new NullLogger();
    }

    public function addMessageHandler(MessageHandlerInterface $handler): void
    {
        $this->protocol->addMessageHandler($handler);
    }

    public function handleRequest(array $request): array
    {
        try {
            return $this->protocol->handleRequest($request);
        } catch (\Throwable $e) {
            $this->logger->error('Request handling failed', ['error' => $e->getMessage(), 'request' => $request]);
            $jsonRpc = new \A2A\Utils\JsonRpc();
            return $jsonRpc->createError($request['id'] ?? null, 'Internal error: ' . $e->getMessage(), \A2A\Exceptions\A2AErrorCodes::INTERNAL_ERROR);
        }
    }

    public function getAgentCard(): AgentCard
    {
        return $this->protocol->getAgentCard();
    }
}
