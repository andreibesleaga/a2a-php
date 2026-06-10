<?php

declare(strict_types=1);

namespace A2A\Tests\Models;

use A2A\Models\PushNotificationAuthenticationInfo;
use PHPUnit\Framework\TestCase;
use A2A\Models\PushNotificationConfig;

class PushNotificationConfigTest extends TestCase
{
    public function testCreateConfig(): void
    {
        $authInfo = new PushNotificationAuthenticationInfo(['bearer']);
        $config = new PushNotificationConfig(
            'https://example.com/webhook',
            'config-123',
            'token-456',
            $authInfo
        );

        $this->assertEquals('https://example.com/webhook', $config->getUrl());
        $this->assertEquals('config-123', $config->getId());
        $this->assertEquals('token-456', $config->getToken());
        $this->assertSame($authInfo, $config->getAuthentication());
    }

    public function testCreateMinimalConfig(): void
    {
        $config = new PushNotificationConfig('https://example.com/webhook');

        $this->assertEquals('https://example.com/webhook', $config->getUrl());
        $this->assertNull($config->getId());
        $this->assertNull($config->getToken());
        $this->assertNull($config->getAuthentication());
    }

    public function testToArray(): void
    {
        $authInfo = new PushNotificationAuthenticationInfo(['bearer'], 'creds');
        $config = new PushNotificationConfig(
            'https://example.com/webhook',
            'config-123',
            'token-456',
            $authInfo
        );

        $expected = [
            'url' => 'https://example.com/webhook',
            'id' => 'config-123',
            'token' => 'token-456',
            'authentication' => [
                'schemes' => ['bearer'],
                'credentials' => 'creds',
            ]
        ];

        $this->assertEquals($expected, $config->toArray());
    }

    public function testFromArray(): void
    {
        $data = [
            'url' => 'https://example.com/webhook',
            'id' => 'config-789',
            'token' => 'token-abc',
            'authentication' => ['schemes' => ['api-key']]
        ];

        $config = PushNotificationConfig::fromArray($data);

        $this->assertEquals('https://example.com/webhook', $config->getUrl());
        $this->assertEquals('config-789', $config->getId());
        $this->assertEquals('token-abc', $config->getToken());
        $this->assertInstanceOf(PushNotificationAuthenticationInfo::class, $config->getAuthentication());
        $this->assertEquals(['api-key'], $config->getAuthentication()->toArray()['schemes']);
    }
}
