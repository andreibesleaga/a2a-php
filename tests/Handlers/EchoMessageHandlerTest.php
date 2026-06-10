<?php

declare(strict_types=1);

namespace A2A\Tests\Handlers;

use A2A\Models\DataPart;
use PHPUnit\Framework\TestCase;
use A2A\Handlers\EchoMessageHandler;
use A2A\Models\v030\Message;
use A2A\Models\TextPart;

class EchoMessageHandlerTest extends TestCase
{
    private EchoMessageHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new EchoMessageHandler();
    }

    public function testCanHandleTextMessage(): void
    {
        $message = Message::createUserMessage('Hello');
        $this->assertTrue($this->handler->canHandle($message));
    }

    public function testCannotHandleNonTextMessage(): void
    {
        $message = new Message('msg-1', 'user', [new DataPart(['data' => 'some data'])]);
        $this->assertFalse($this->handler->canHandle($message));
    }

    public function testHandleMessage(): void
    {
        $message = Message::createUserMessage('Hello World');
        $result = $this->handler->handle($message, 'test-agent');

        $this->assertEquals('processed', $result['status']);
        $this->assertEquals('Hello World', $result['echo']);
        $this->assertEquals('test-agent', $result['from']);
        $this->assertEquals($message->getMessageId(), $result['message_id']);
        $this->assertArrayHasKey('timestamp', $result);
    }
}
