<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Defines the configuration for setting up push notifications for task updates.
 *
 * @see https://a2a-protocol.org/dev/specification/#68-pushnotificationconfig-object
 */
class PushNotificationConfig
{
    private string $url;
    private ?string $id;
    private ?string $token;
    private ?PushNotificationAuthenticationInfo $authentication;

    public function __construct(
        string $url,
        ?string $id = null,
        ?string $token = null,
        ?PushNotificationAuthenticationInfo $authentication = null
    ) {
        $this->url = $url;
        $this->id = $id;
        $this->token = $token;
        $this->authentication = $authentication;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getAuthentication(): ?PushNotificationAuthenticationInfo
    {
        return $this->authentication;
    }

    public function toArray(): array
    {
        $data = [
            'url' => $this->url,
        ];
        if ($this->id !== null) {
            $data['id'] = $this->id;
        }
        if ($this->token !== null) {
            $data['token'] = $this->token;
        }
        if ($this->authentication !== null) {
            $data['authentication'] = $this->authentication->toArray();
        }
        return $data;
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['url']) || !is_string($data['url']) || $data['url'] === '') {
            throw new \InvalidArgumentException('PushNotificationConfig requires a non-empty string "url" field');
        }

        $authentication = null;
        if (isset($data['authentication'])) {
            $authentication = PushNotificationAuthenticationInfo::fromArray($data['authentication']);
        }

        return new self(
            $data['url'],
            $data['id'] ?? null,
            $data['token'] ?? null,
            $authentication
        );
    }
}
