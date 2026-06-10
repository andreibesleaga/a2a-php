<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Defines base properties for a file.
 *
 * @see https://a2a-protocol.org/dev/specification/#66-filebase-object
 */
abstract class FileBase
{
    protected ?string $name;
    protected ?string $mimeType;

    public function __construct(?string $name = null, ?string $mimeType = null)
    {
        $this->name = $name;
        $this->mimeType = $mimeType;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    abstract public function toArray(): array;
}
