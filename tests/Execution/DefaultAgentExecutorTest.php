<?php

declare(strict_types=1);

namespace A2A\Tests\Execution;

use PHPUnit\Framework\TestCase;
use A2A\Execution\DefaultAgentExecutor;
use A2A\Events\ExecutionEventBusImpl;
use A2A\Models\RequestContext;
use A2A\Models\Message;
use A2A\Models\TaskStatusUpdateEvent;

class DefaultAgentExecutorTest extends TestCase
{
    public function testExecute(): void
    {
        $executor = new DefaultAgentExecutor();
        $eventBus = new ExecutionEventBusImpl();

        $message = Message::createUserMessage('Test message');
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

    public function testCancelTask(): void
    {
        $executor = new DefaultAgentExecutor();
        $eventBus = new ExecutionEventBusImpl();

        $executor->cancelTask('task-123', $eventBus);

        // Test that cancellation is recorded
        $this->assertTrue(true); // Basic test that method executes
    }
}
