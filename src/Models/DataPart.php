<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Represents a structured data segment (e.g., JSON) within a message or artifact.
 *
 * @see https://a2a-protocol.org/dev/specification/#653-datapart-object
 */
class DataPart implements PartInterface
{
    private array $data;
    private ?array $metadata;

    public function __construct(array $data, ?array $metadata = null)
    {
        $this->data = $data;
        $this->metadata = $metadata;
    }

    public function getKind(): string
    {
        return 'data';
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        $data = [
            'kind' => 'data',
            'data' => $this->data,
        ];

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['data'],
            $data['metadata'] ?? null
        );
    }
}
