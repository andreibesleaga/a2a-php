<?php

declare(strict_types=1);

namespace A2A\Models\Security;

/**
 * OAuth2SecurityScheme model.
 *
 * @see https://swagger.io/specification/#oauth2-security-scheme
 */
class OAuth2SecurityScheme implements SecurityScheme
{
    private string $type = 'oauth2';
    private OAuthFlows $flows;
    private ?string $description;

    public function __construct(OAuthFlows $flows, ?string $description = null)
    {
        $this->flows = $flows;
        $this->description = $description;
    }

    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'flows' => $this->flows->toArray(),
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            OAuthFlows::fromArray($data['flows']),
            $data['description'] ?? null
        );
    }
}
