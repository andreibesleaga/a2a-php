<?php

declare(strict_types=1);

namespace A2A\Models\Security;

/**
 * Defines a security scheme that can be used to secure an agent's endpoints.
 * This is a marker interface for different security scheme types, following the OpenAPI 3.0 specification.
 *
 * @see https://swagger.io/specification/#security-scheme-object
 */
interface SecurityScheme
{
    /**
     * Converts the security scheme object to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
