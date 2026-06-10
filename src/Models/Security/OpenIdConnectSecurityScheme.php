<?php

declare(strict_types=1);

namespace A2A\Models\Security;

/**
 * OpenIdConnectSecurityScheme model.
 *
 * @see https://swagger.io/specification/#open-id-connect-security-scheme
 */
class OpenIdConnectSecurityScheme implements SecurityScheme
{
    private string $type = 'openIdConnect';
    private string $openIdConnectUrl;
    private ?string $description;

    public function __construct(string $openIdConnectUrl, ?string $description = null)
    {
        $this->openIdConnectUrl = $openIdConnectUrl;
        $this->description = $description;
    }

    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'openIdConnectUrl' => $this->openIdConnectUrl,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['openIdConnectUrl'],
            $data['description'] ?? null
        );
    }
}
