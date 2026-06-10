<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\A2AProtocol_v030;
use A2A\Interfaces\MessageHandlerInterface;
use A2A\Models\TextPart;
use PHPUnit\Framework\TestCase;
use A2A\A2AClient;
use A2A\A2AServer;
use A2A\Models\v030\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\v030\Message;
use A2A\Utils\HttpClient;

class EndToEndTest extends TestCase
{
    public function testCompleteAgentScenario(): void
    {
        // Setup agent
        $capabilities = new AgentCapabilities(true, false);
        $skill = new AgentSkill('chat', 'Chat', 'Chat capability', ['chat']);

        $agentCard = new AgentCard(
            'E2E Agent',
            'End-to-end test agent',
            'https://example.com/agent',
            '1.0.0',
            $capabilities,
            ['text/plain'],
            ['application/json'],
            [$skill]
        );

        // Setup server
        $protocol = new A2AProtocol_v030($agentCard);
        $server = new A2AServer($protocol);

        $messageHandler = new class implements MessageHandlerInterface {
            public function canHandle(Message $message): bool
            {
                return true;
            }

            public function handle(Message $message, string $fromAgent): array
            {
                $textContent = '';
                foreach ($message->getParts() as $part) {
                    if ($part instanceof TextPart) {
                        $textContent = $part->getText();
                        break;
                    }
                }
                TestCase::assertEquals('Hello Agent', $textContent);
                return [
                    'status' => ['state' => 'completed'],
                    'metadata' => [
                        'handled' => true,
                        'fromAgent' => $fromAgent
                    ]
                ];
            }
        };
        $protocol->addMessageHandler($messageHandler);

        // Setup client with mock HTTP
        $httpClient = new class extends HttpClient {
            private A2AServer $server;

            public function setServer(A2AServer $server): void
            {
                $this->server = $server;
            }

            public function post(string $url, array $data): array
            {
                return $this->server->handleRequest($data);
            }
        };

        $httpClient->setServer($server);
        $client = new A2AClient($agentCard, $httpClient);

        // Test complete flow
        $message = Message::createUserMessage('Hello Agent');
        $response = $client->sendMessage('http://test', $message);

        $result = $response['result'];
        $this->assertSame('task', $result['kind']);
        $this->assertEquals('completed', $result['status']['state']);
        $this->assertNotEmpty($result['history']);
        $this->assertTrue($result['metadata']['handled']);
        $this->assertSame('E2E Agent', $result['metadata']['fromAgent'] ?? null);
    }
}
