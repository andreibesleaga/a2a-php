<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\A2AErrorCodes;
use A2A\Models\v030\AgentCard;
use A2A\Utils\JsonRpc;

/**
 * Handles the "agent/getAuthenticatedExtendedCard" method.
 *
 * Access is gated by Bearer/X-API-Key credentials; when the
 * A2A_DEMO_AUTH_TOKEN env var is set, the provided token must match.
 */
class AuthenticatedExtendedCardHandler implements RequestHandlerInterface
{
    private AgentCard $agentCard;

    public function __construct(AgentCard $agentCard)
    {
        $this->agentCard = $agentCard;
    }

    public function handle(array $params, mixed $requestId): array
    {
        $jsonRpc = new JsonRpc();

        if (!$this->agentCard->getSupportsAuthenticatedExtendedCard()) {
            return $jsonRpc->createError(
                $requestId,
                A2AErrorCodes::getErrorMessage(A2AErrorCodes::AUTHENTICATED_EXTENDED_CARD_NOT_CONFIGURED),
                A2AErrorCodes::AUTHENTICATED_EXTENDED_CARD_NOT_CONFIGURED
            );
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $hasCredentials = trim($authHeader) !== '' || trim($apiKeyHeader) !== '';

        if (!$hasCredentials) {
            if (function_exists('header')) {
                header('WWW-Authenticate: Bearer realm="A2A"');
            }

            return $jsonRpc->createError(
                $requestId,
                'Authentication required for authenticated extended card',
                A2AErrorCodes::AUTHENTICATED_EXTENDED_CARD_NOT_CONFIGURED
            );
        }

        $expectedToken = getenv('A2A_DEMO_AUTH_TOKEN');
        $providedToken = '';

        if (trim($authHeader) !== '') {
            $providedToken = trim(preg_replace('/^Bearer\s+/i', '', $authHeader));
        } elseif (trim($apiKeyHeader) !== '') {
            $providedToken = trim($apiKeyHeader);
        }

        if ($expectedToken !== false && $expectedToken !== '' && $providedToken !== $expectedToken) {
            if (function_exists('header')) {
                header('WWW-Authenticate: Bearer realm="A2A", error="invalid_token"');
            }

            return $jsonRpc->createError(
                $requestId,
                'Invalid authentication credentials',
                A2AErrorCodes::AUTHENTICATED_EXTENDED_CARD_NOT_CONFIGURED
            );
        }

        // NOTE: In a real implementation, this would return an extended card
        // with additional details based on the authenticated principal.
        return $jsonRpc->createResponse($requestId, $this->agentCard->toArray());
    }
}
