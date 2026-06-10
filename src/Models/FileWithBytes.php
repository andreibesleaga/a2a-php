<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Represents a file with its content provided directly as a base64-encoded string.
 *
 * @see https://a2a-protocol.org/dev/specification/#661-filewithbytes-object
 */
class FileWithBytes extends FileBase
{
    private string $bytes;

    public function __construct(string $bytes, ?string $name = null, ?string $mimeType = null)
    {
        parent::__construct($name, $mimeType);
        $this->bytes = $bytes;
    }

    public function getBytes(): string
    {
        return $this->bytes;
    }

    public function toArray(): array
    {
        $data = [
            'bytes' => $this->bytes,
        ];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['bytes'],
            $data['name'] ?? null,
            $data['mimeType'] ?? null
        );
    }
}
