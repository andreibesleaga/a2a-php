<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Interface for all Part types.
 */
interface PartInterface
{
    public function getKind(): string;

    public function toArray(): array;
}
