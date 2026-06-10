<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * AgentCardSignature represents a JWS signature of an AgentCard.
 * This follows the JSON format of an RFC 7515 JSON Web Signature (JWS).
 */
class AgentCardSignature
{
    private string $protected;
    private string $signature;
    private ?array $header;

    public function __construct(string $protected, string $signature, ?array $header = null)
    {
        $this->protected = $protected;
        $this->signature = $signature;
        $this->header = $header;
    }

    public function getProtected(): string
    {
        return $this->protected;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getHeader(): ?array
    {
        return $this->header;
    }

    public function toArray(): array
    {
        $data = [
            'protected' => $this->protected,
            'signature' => $this->signature,
        ];

        if ($this->header !== null) {
            $data['header'] = $this->header;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['protected'],
            $data['signature'],
            $data['header'] ?? null
        );
    }
}
