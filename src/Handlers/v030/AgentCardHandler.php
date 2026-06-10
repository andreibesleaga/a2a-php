<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Models\v030\AgentCard;
use A2A\Utils\JsonRpc;

/**
 * Handles the "get_agent_card" method.
 */
class AgentCardHandler implements RequestHandlerInterface
{
    private AgentCard $agentCard;

    public function __construct(AgentCard $agentCard)
    {
        $this->agentCard = $agentCard;
    }

    public function handle(array $params, mixed $requestId): array
    {
        return (new JsonRpc())->createResponse($requestId, $this->agentCard->toArray());
    }
}
