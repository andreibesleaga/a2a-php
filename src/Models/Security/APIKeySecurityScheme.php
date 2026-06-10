<?php

declare(strict_types=1);

namespace A2A\Models\Security;

/**
 * APIKeySecurityScheme model.
 *
 * @see https://swagger.io/specification/#api-key-security-scheme
 */
class APIKeySecurityScheme implements SecurityScheme
{
    private string $type = 'apiKey';
    private string $name;
    private string $in;
    private ?string $description;

    public function __construct(string $name, string $in, ?string $description = null)
    {
        $this->name = $name;
        $this->in = $in;
        $this->description = $description;
    }

    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'name' => $this->name,
            'in' => $this->in,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['in'],
            $data['description'] ?? null
        );
    }
}
