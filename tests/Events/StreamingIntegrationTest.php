<?php

declare(strict_types=1);

namespace A2A\Tests\Events;

use PHPUnit\Framework\TestCase;
use A2A\Events\ExecutionEventBusImpl;
use A2A\Execution\DefaultAgentExecutor;
use A2A\Models\RequestContext;
use A2A\Models\Message;
use A2A\Models\TaskStatusUpdateEvent;

class StreamingIntegrationTest extends TestCase
{
    public function testStreamingEventFlow(): void
    {
        $eventBus = new ExecutionEventBusImpl();
        $executor = new DefaultAgentExecutor();

        $message = Message::createUserMessage('Test streaming');
        $context = new RequestContext($message, 'task-123', 'ctx-123');

        $events = [];
        $eventBus->subscribe(
            'task-123',
            function ($event) use (&$events) {
                $events[] = $event;
            }
        );

        $executor->execute($context, $eventBus);

        $this->assertGreaterThan(0, count($events));
        $this->assertInstanceOf(TaskStatusUpdateEvent::class, $events[1]);
    }

    public function testConcurrentStreams(): void
    {
        $eventBus = new ExecutionEventBusImpl();
        $executor = new DefaultAgentExecutor();

        $events1 = [];
        $events2 = [];

        $eventBus->subscribe(
            'task-1',
            function ($event) use (&$events1) {
                $events1[] = $event;
            }
        );

        $eventBus->subscribe(
            'task-2',
            function ($event) use (&$events2) {
                $events2[] = $event;
            }
        );

        $message1 = Message::createUserMessage('Stream 1');
        $context1 = new RequestContext($message1, 'task-1', 'ctx-1');

        $message2 = Message::createUserMessage('Stream 2');
        $context2 = new RequestContext($message2, 'task-2', 'ctx-2');

        $executor->execute($context1, $eventBus);
        $executor->execute($context2, $eventBus);

        $this->assertGreaterThan(0, count($events1));
        $this->assertGreaterThan(0, count($events2));
    }
}
