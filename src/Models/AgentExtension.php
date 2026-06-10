<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * A declaration of an extension supported by an Agent
 */
class AgentExtension
{
    private string $uri;
    private ?string $description = null;
    private ?bool $required = null;
    private ?array $params = null;

    public function __construct(
        string $uri,
        ?string $description = null,
        ?array $params = null,
        ?bool $required = null
    ) {
        $this->uri = $uri;
        $this->description = $description;
        $this->params = $params;
        $this->required = $required ?? false;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setRequired(?bool $required): void
    {
        $this->required = $required;
    }

    public function isRequired(): ?bool
    {
        return $this->required;
    }

    public function setParams(?array $params): void
    {
        $this->params = $params;
    }

    public function getParams(): ?array
    {
        return $this->params;
    }

    public function toArray(): array
    {
        $result = [
            'uri' => $this->uri
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->params !== null) {
            $result['params'] = $this->params;
        }

        if ($this->required !== null) {
            $result['required'] = $this->required;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['uri'],
            $data['description'] ?? null,
            $data['params'] ?? null,
            $data['required'] ?? null
        );
    }
}
