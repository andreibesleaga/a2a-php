<?php

declare(strict_types=1);

namespace A2A\Models\Security;

/**
 * HTTPAuthSecurityScheme model.
 *
 * @see https://swagger.io/specification/#http-security-scheme
 */
class HTTPAuthSecurityScheme implements SecurityScheme
{
    private string $type = 'http';
    private string $scheme;
    private ?string $bearerFormat;
    private ?string $description;

    public function __construct(string $scheme, ?string $bearerFormat = null, ?string $description = null)
    {
        $this->scheme = $scheme;
        $this->bearerFormat = $bearerFormat;
        $this->description = $description;
    }

    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'scheme' => $this->scheme,
        ];

        if ($this->bearerFormat !== null) {
            $data['bearerFormat'] = $this->bearerFormat;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['scheme'],
            $data['bearerFormat'] ?? null,
            $data['description'] ?? null
        );
    }
}
