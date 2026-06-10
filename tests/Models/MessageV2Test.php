<?php

declare(strict_types=1);

namespace A2A\Tests\Models;

use PHPUnit\Framework\TestCase;
use A2A\Models\Message;
use A2A\Models\TextPart;

class MessageV2Test extends TestCase
{
    public function testCreateUserMessage(): void
    {
        $message = Message::createUserMessage('Hello World');

        $this->assertEquals('user', $message->getRole());
        $this->assertEquals('message', $message->getKind());
        $this->assertNotEmpty($message->getMessageId());

        $parts = $message->getParts();
        $this->assertCount(1, $parts);
        $this->assertInstanceOf(TextPart::class, $parts[0]);
        $this->assertEquals('Hello World', $parts[0]->getText());
    }

    public function testCreateAgentMessage(): void
    {
        $message = Message::createAgentMessage('Hello User');

        $this->assertEquals('agent', $message->getRole());
        $this->assertEquals('message', $message->getKind());

        $parts = $message->getParts();
        $this->assertCount(1, $parts);
        $this->assertInstanceOf(TextPart::class, $parts[0]);
        $this->assertEquals('Hello User', $parts[0]->getText());
    }

    public function testToArray(): void
    {
        $message = Message::createUserMessage('Test');
        $array = $message->toArray();

        $this->assertEquals('message', $array['kind']);
        $this->assertEquals('user', $array['role']);
        $this->assertArrayHasKey('messageId', $array);
        $this->assertArrayHasKey('parts', $array);
        $this->assertEquals('text', $array['parts'][0]['kind']);
    }
}
