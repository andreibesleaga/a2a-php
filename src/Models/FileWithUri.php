<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Represents a file with its content located at a specific URI.
 *
 * @see https://a2a-protocol.org/dev/specification/#662-filewithuri-object
 */
class FileWithUri extends FileBase
{
    private string $uri;

    public function __construct(string $uri, ?string $name = null, ?string $mimeType = null)
    {
        parent::__construct($name, $mimeType);
        $this->uri = $uri;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function toArray(): array
    {
        $data = [
            'uri' => $this->uri,
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
            $data['uri'],
            $data['name'] ?? null,
            $data['mimeType'] ?? null
        );
    }
}
