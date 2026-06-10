<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\Models\TextPart;
use PHPUnit\Framework\TestCase;
use A2A\Models\Message;
use A2A\Models\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\Task;
use A2A\Models\TaskState;
use A2A\Utils\JsonRpc;

class EdgeCaseTest extends TestCase
{
    public function testMessageWithEmptyContent(): void
    {
        $message = Message::createUserMessage('');
        $this->assertNotEmpty($message->getMessageId());
        $parts = $message->getParts();
        $this->assertCount(1, $parts);
        $this->assertInstanceOf(TextPart::class, $parts[0]);
        $this->assertEquals('', $parts[0]->getText());
    }

    public function testMessageFromArrayWithMissingFields(): void
    {
        $data = ['messageId' => 'test-123', 'role' => 'user', 'parts' => []];
        $message = Message::fromArray($data);

        $this->assertEquals('test-123', $message->getMessageId());
        $this->assertEquals('user', $message->getRole());
        $this->assertNotEmpty($message->getMessageId());
    }

    public function testAgentCardWithMinimalData(): void
    {
        $capabilities = new AgentCapabilities();

        $card = new AgentCard(
            'Minimal Agent',
            'Description',
            'https://example.com/agent',
            '1.0.0',
            $capabilities,
            [],
            [],
            []
        );

        $this->assertEquals('Minimal Agent', $card->getName());
        $this->assertEquals('Description', $card->getDescription());
        $this->assertEquals('1.0.0', $card->getVersion());
    }

    public function testTaskStateTransitions(): void
    {
        $status = new \A2A\Models\TaskStatus(\A2A\Models\TaskState::SUBMITTED);
        $task = new \A2A\Models\Task('task-1', 'ctx-1', $status);

        $states = [
            \A2A\Models\TaskState::SUBMITTED,
            \A2A\Models\TaskState::WORKING,
            \A2A\Models\TaskState::INPUT_REQUIRED,
            \A2A\Models\TaskState::COMPLETED
        ];

        foreach ($states as $state) {
            $task->setStatus(new \A2A\Models\TaskStatus($state));
            $this->assertEquals($state, $task->getStatus()->getState());
        }
    }

    public function testJsonRpcWithNullId(): void
    {
        $jsonRpc = new JsonRpc();
        $request = $jsonRpc->createRequest('test_method', [], null);

        $this->assertEquals('2.0', $request['jsonrpc']);
        $this->assertEquals('test_method', $request['method']);
        self::assertArrayNotHasKey('id', $request);
    }

    public function testJsonRpcErrorHandling(): void
    {
        $jsonRpc = new JsonRpc();
        $error = $jsonRpc->createError(1, 'Test error', -32000);

        $this->assertEquals('2.0', $error['jsonrpc']);
        $this->assertEquals(1, $error['id']);
        $this->assertEquals('Test error', $error['error']['message']);
        $this->assertEquals(-32000, $error['error']['code']);
    }

    public function testLargeMessageContent(): void
    {
        $largeContent = str_repeat('A', 10000);
        $message = Message::createUserMessage($largeContent);

        $parts = $message->getParts();
        $this->assertInstanceOf(TextPart::class, $parts[0]);
        $this->assertEquals($largeContent, $parts[0]->getText());
        $this->assertEquals(10000, strlen($parts[0]->getText()));
    }

    public function testSpecialCharactersInMessage(): void
    {
        $specialContent = "Hello 世界! 🌍 Special chars: àáâãäå";
        $message = Message::createUserMessage($specialContent);

        $parts = $message->getParts();
        $this->assertInstanceOf(TextPart::class, $parts[0]);
        $this->assertEquals($specialContent, $parts[0]->getText());

        $array = $message->toArray();
        $reconstructed = Message::fromArray($array);
        $reconstructedParts = $reconstructed->getParts();
        $this->assertInstanceOf(TextPart::class, $reconstructedParts[0]);
        $this->assertEquals($specialContent, $reconstructedParts[0]->getText());
    }

    public function testConcurrentTaskCreation(): void
    {
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $status = new \A2A\Models\TaskStatus(\A2A\Models\TaskState::SUBMITTED);
            $tasks[] = new \A2A\Models\Task("task-$i", "ctx-$i", $status);
        }

        $ids = array_map(fn($task) => $task->getId(), $tasks);
        $this->assertEquals(100, count(array_unique($ids)));
    }
}
