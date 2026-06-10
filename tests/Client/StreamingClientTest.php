<?php

declare(strict_types=1);

namespace A2A\Tests\Client;

use PHPUnit\Framework\TestCase;
use A2A\Client\StreamingClient;
use A2A\Models\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\Message;

class StreamingClientTest extends TestCase
{
    private StreamingClient $client;

    protected function setUp(): void
    {
        $capabilities = new AgentCapabilities(true, false, false);
        $skill = new AgentSkill('test', 'Test', 'Test skill', ['test']);

        $agentCard = new AgentCard(
            'Test Agent',
            'Test Description',
            'https://example.com/agent',
            '1.0.0',
            $capabilities,
            ['text'],
            ['text'],
            [$skill]
        );

        $this->client = new StreamingClient($agentCard);
    }

    public function testStreamingClientCreation(): void
    {
        $this->assertInstanceOf(StreamingClient::class, $this->client);
    }

    public function testSendMessageStreamMethod(): void
    {
        $message = Message::createUserMessage('Test streaming');

        // Test that method exists and can be called
        $this->assertTrue(method_exists($this->client, 'sendMessageStream'));
        $this->assertTrue(method_exists($this->client, 'resubscribeTask'));
    }
}
