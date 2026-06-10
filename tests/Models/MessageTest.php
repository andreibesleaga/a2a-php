<?php

declare(strict_types=1);

namespace A2A\Tests\Models;

use A2A\Models\DataPart;
use A2A\Models\PartInterface;
use A2A\Models\TextPart;
use PHPUnit\Framework\TestCase;
use A2A\Models\Message;

class MessageTest extends TestCase
{
    public function testCreateUserMessage(): void
    {
        $message = Message::createUserMessage('Hello World');

        $this->assertEquals('user', $message->getRole());
        $this->assertNotEmpty($message->getMessageId());
        $this->assertCount(1, $message->getParts());
        $this->assertInstanceOf(TextPart::class, $message->getParts()[0]);
    }

    public function testCreateAgentMessageWithId(): void
    {
        $message = Message::createAgentMessage('Test', 'custom-id');

        $this->assertEquals('agent', $message->getRole());
        $this->assertEquals('custom-id', $message->getMessageId());
    }

    public function testSetMetadata(): void
    {
        $message = Message::createUserMessage('Test');
        $message->setMetadata(['priority' => 'high', 'sender' => 'agent-123']);

        $expected = ['priority' => 'high', 'sender' => 'agent-123'];
        $this->assertEquals($expected, $message->getMetadata());
    }

    public function testAddPart(): void
    {
        $message = Message::createUserMessage('Test');
        $part = new DataPart(['status' => 'ok']);
        $message->addPart($part);

        $this->assertCount(2, $message->getParts());
        $this->assertInstanceOf(TextPart::class, $message->getParts()[0]);
        $this->assertInstanceOf(DataPart::class, $message->getParts()[1]);
    }

    public function testToArray(): void
    {
        $message = Message::createUserMessage('Test content', 'msg-123');
        $message->setMetadata(['key' => 'value']);
        $message->setContextId('ctx-1');
        $message->setTaskId('task-1');

        $array = $message->toArray();

        $this->assertEquals('msg-123', $array['messageId']);
        $this->assertEquals('message', $array['kind']);
        $this->assertEquals('user', $array['role']);
        $this->assertEquals('ctx-1', $array['contextId']);
        $this->assertEquals('task-1', $array['taskId']);
        $this->assertEquals(['key' => 'value'], $array['metadata']);
        $this->assertCount(1, $array['parts']);
        $this->assertEquals('text', $array['parts'][0]['kind']);
    }

    public function testFromArray(): void
    {
        $data = [
            'messageId' => 'msg-456',
            'role' => 'agent',
            'kind' => 'message',
            'parts' => [
                ['kind' => 'text', 'text' => 'Hello'],
                ['kind' => 'data', 'data' => ['code' => 200]],
            ],
            'metadata' => ['lang' => 'en']
        ];

        $message = Message::fromArray($data);

        $this->assertEquals('msg-456', $message->getMessageId());
        $this->assertEquals('agent', $message->getRole());
        $this->assertEquals(['lang' => 'en'], $message->getMetadata());
        $this->assertCount(2, $message->getParts());
        $this->assertInstanceOf(TextPart::class, $message->getParts()[0]);
        $this->assertInstanceOf(DataPart::class, $message->getParts()[1]);
        $this->assertEquals('Hello', $message->getParts()[0]->getText());
    }

    public function testMessageExtensions(): void
    {
        $message = Message::createUserMessage('Test');
        $message->setExtensions(['extension1', 'extension2']);

        $this->assertEquals(['extension1', 'extension2'], $message->getExtensions());
    }

    public function testReferenceTaskIds(): void
    {
        $message = Message::createUserMessage('Test');
        $message->setReferenceTaskIds(['task-1', 'task-2']);

        $this->assertEquals(['task-1', 'task-2'], $message->getReferenceTaskIds());
    }

    public function testContextAndTaskIds(): void
    {
        $message = Message::createUserMessage('Test');
        $message->setContextId('context-123');
        $message->setTaskId('task-456');

        $this->assertEquals('context-123', $message->getContextId());
        $this->assertEquals('task-456', $message->getTaskId());
    }
}
