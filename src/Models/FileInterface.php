<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Interface for file content representations
 */
interface FileInterface
{
    public function getName(): ?string;
    public function getMimeType(): ?string;
    public function toArray(): array;
}
