<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\A2AProtocol_v030;
use A2A\Interfaces\MessageHandlerInterface;
use PHPUnit\Framework\TestCase;
use A2A\A2AServer;
use A2A\Models\v030\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\v030\Message;
use A2A\TaskManager;
use A2A\Storage\Storage;

class PerformanceTest extends TestCase
{
    public function testHighVolumeMessageProcessing(): void
    {
        $capabilities = new AgentCapabilities();
        $skill = new AgentSkill('test', 'Test', 'Test skill', ['test']);

        $agentCard = new AgentCard(
            'Performance Test Agent',
            'Performance test description',
            'https://example.com/perf',
            '1.0.0',
            $capabilities,
            ['text/plain'],
            ['application/json'],
            [$skill]
        );

        $protocol = new A2AProtocol_v030($agentCard);
        $server = new A2AServer($protocol);

        $messageHandler = new class implements MessageHandlerInterface {
            public int $processedCount = 0;

            public function canHandle(Message $message): bool
            {
                return true;
            }
            public function handle(Message $message, string $fromAgent): array
            {
                $this->processedCount++;
                return ['status' => 'ok'];
            }
        };
        $protocol->addMessageHandler($messageHandler);

        $startTime = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $message = Message::createUserMessage("Message $i");
            $request = [
                'jsonrpc' => '2.0',
                'method' => 'message/send',
                'params' => [
                    'from' => 'client-perf',
                    'message' => $message->toArray()
                ],
                'id' => $i
            ];

            $server->handleRequest($request);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->assertEquals(1000, $messageHandler->processedCount);
        $this->assertLessThan(6.0, $duration, 'Processing 1000 messages should take less than 6 seconds');
    }

    public function testTaskManagerMemoryUsage(): void
    {
        $taskManager = new TaskManager(new Storage('array'));
        $initialMemory = memory_get_usage();

        // Create many tasks
        for ($i = 0; $i < 1000; $i++) {
            $taskManager->createTask("Task $i", ['index' => $i]);
        }

        $afterCreationMemory = memory_get_usage();
        $memoryIncrease = $afterCreationMemory - $initialMemory;

        // Memory increase should be reasonable (less than 10MB for 1000 tasks)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease);
    }

    public function testMessageSerializationPerformance(): void
    {
        $message = Message::createUserMessage('Performance test message');
        $message->setMetadata(['test' => 'value']);

        $startTime = microtime(true);

        for ($i = 0; $i < 10000; $i++) {
            $array = $message->toArray();
            $reconstructed = Message::fromArray($array);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->assertLessThan(1.0, $duration, 'Serialization/deserialization should be fast');
    }
}
