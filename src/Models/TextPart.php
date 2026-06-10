<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Represents a text segment within a message or artifact.
 *
 * @see https://a2a-protocol.org/dev/specification/#651-textpart-object
 */
class TextPart implements PartInterface
{
    private string $text;
    private ?array $metadata;

    public function __construct(string $text, ?array $metadata = null)
    {
        $this->text = $text;
        $this->metadata = $metadata;
    }

    public function getKind(): string
    {
        return 'text';
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function toArray(): array
    {
        $data = [
            'kind' => 'text',
            'text' => $this->text,
        ];

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['text'],
            $data['metadata'] ?? null
        );
    }
}
