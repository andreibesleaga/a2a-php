<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Represents the service provider of an agent
 */
class AgentProvider
{
    private string $organization;
    private string $url;

    public function __construct(string $organization, string $url)
    {
        $this->organization = $organization;
        $this->url = $url;
    }

    public function getOrganization(): string
    {
        return $this->organization;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function toArray(): array
    {
        return [
            'organization' => $this->organization,
            'url' => $this->url
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['organization'],
            $data['url']
        );
    }
}
