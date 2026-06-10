<?php

declare(strict_types=1);

namespace A2A\Models\Security;

/**
 * OAuthFlow model.
 *
 * @see https://swagger.io/specification/#oauth-flow-object
 */
class OAuthFlow
{
    private ?string $authorizationUrl;
    private ?string $tokenUrl;
    private ?string $refreshUrl;
    private array $scopes;

    public function __construct(
        array $scopes,
        ?string $authorizationUrl = null,
        ?string $tokenUrl = null,
        ?string $refreshUrl = null
    ) {
        $this->scopes = $scopes;
        $this->authorizationUrl = $authorizationUrl;
        $this->tokenUrl = $tokenUrl;
        $this->refreshUrl = $refreshUrl;
    }

    public function toArray(): array
    {
        $data = [
            'scopes' => $this->scopes,
        ];

        if ($this->authorizationUrl !== null) {
            $data['authorizationUrl'] = $this->authorizationUrl;
        }

        if ($this->tokenUrl !== null) {
            $data['tokenUrl'] = $this->tokenUrl;
        }

        if ($this->refreshUrl !== null) {
            $data['refreshUrl'] = $this->refreshUrl;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['scopes'],
            $data['authorizationUrl'] ?? null,
            $data['tokenUrl'] ?? null,
            $data['refreshUrl'] ?? null
        );
    }
}
