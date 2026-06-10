<?php

declare(strict_types=1);

namespace A2A\Models\Security;

/**
 * MutualTLSSecurityScheme model.
 *
 * @see https://swagger.io/specification/#security-scheme-object (note: mutualTLS is not explicitly in OpenAPI 3.0 but is used by A2A)
 */
class MutualTLSSecurityScheme implements SecurityScheme
{
    private string $type = 'mutualTLS';
    private ?string $description;

    public function __construct(?string $description = null)
    {
        $this->description = $description;
    }

    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['description'] ?? null
        );
    }
}
