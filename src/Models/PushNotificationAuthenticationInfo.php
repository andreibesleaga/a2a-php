<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Defines authentication details for a push notification endpoint.
 *
 * @see https://a2a-protocol.org/dev/specification/#69-pushnotificationauthenticationinfo-object
 */
class PushNotificationAuthenticationInfo
{
    /** @var string[] */
    private array $schemes;
    private ?string $credentials;

    public function __construct(array $schemes, ?string $credentials = null)
    {
        $this->schemes = $schemes;
        $this->credentials = $credentials;
    }

    public function toArray(): array
    {
        $data = [
            'schemes' => $this->schemes,
        ];

        if ($this->credentials !== null) {
            $data['credentials'] = $this->credentials;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['schemes']) || !is_array($data['schemes'])) {
            throw new \InvalidArgumentException('PushNotificationAuthenticationInfo requires a "schemes" array field');
        }

        return new self(
            $data['schemes'],
            $data['credentials'] ?? null
        );
    }
}