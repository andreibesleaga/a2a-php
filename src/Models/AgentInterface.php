<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Declares a combination of a target URL and a transport protocol for interacting with the agent.
 */
class AgentInterface
{
    private string $url;
    private string $transport;

    public function __construct(string $url, string $transport)
    {
        $this->url = $url;
        $this->transport = $transport;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'transport' => $this->transport,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['url'],
            $data['transport']
        );
    }

    public static function jsonRpc(string $url): self
    {
        return new self($url, 'JSONRPC');
    }

    public static function grpc(string $url): self
    {
        return new self($url, 'GRPC');
    }

    public static function httpJson(string $url): self
    {
        return new self($url, 'HTTP_JSON');
    }
}
