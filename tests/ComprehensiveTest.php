<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\Models\DataPart;
use A2A\Models\FilePart;
use A2A\Models\Part;
use A2A\Models\TaskStatus;
use A2A\Models\TextPart;
use PHPUnit\Framework\TestCase;
use A2A\Models\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\AgentProvider;
use A2A\Models\Message;
use A2A\Models\Task;
use A2A\Models\TaskState;
use A2A\Models\FileWithBytes;
use A2A\Events\ExecutionEventBusImpl;
use A2A\Execution\DefaultAgentExecutor;
use A2A\Models\RequestContext;
use A2A\Exceptions\A2AErrorCodes;
use A2A\Models\TaskStatusUpdateEvent;

class ComprehensiveTest extends TestCase
{
    public function testCompleteA2AImplementation(): void
    {
        // 1. Test AgentCard compliance
        $capabilities = new AgentCapabilities(true, true);
        $skill = new AgentSkill('test', 'Test Skill', 'Test description', ['test']);
        $provider = new AgentProvider('Test Org', 'https://test.com');

        $agentCard = new AgentCard(
            'Test Agent',
            'Test Description',
            'https://example.com/agent',
            '1.0.0',
            $capabilities,
            ['text/plain'],
            ['application/json'],
            [$skill],
            '0.3.0'
        );
        $agentCard->setProvider($provider);

        $cardArray = $agentCard->toArray();
        $this->assertEquals('0.3.0', $cardArray['protocolVersion']);
        $this->assertTrue($cardArray['capabilities']['streaming']);

        // 2. Test Message compliance
        $message = Message::createUserMessage('Hello World');
        $messageArray = $message->toArray();
        $this->assertEquals('message', $messageArray['kind']);
        $this->assertEquals('user', $messageArray['role']);
        $this->assertArrayHasKey('messageId', $messageArray);

        // 3. Test Task compliance
        $taskStatus = new TaskStatus(TaskState::SUBMITTED);
        $task = new Task('task-123', 'ctx-123', $taskStatus);
        $taskArray = $task->toArray();
        $this->assertEquals('task', $taskArray['kind']);
        $this->assertEquals('ctx-123', $taskArray['contextId']);

        // 4. Test Part types using the factory
        $textPart = Part::fromArray(['kind' => 'text', 'text' => 'Hello']);
        $filePart = Part::fromArray(['kind' => 'file', 'file' => ['bytes' => 'base64data']]);
        $dataPart = Part::fromArray(['kind' => 'data', 'data' => ['key' => 'value']]);

        $this->assertInstanceOf(TextPart::class, $textPart);
        $this->assertInstanceOf(FilePart::class, $filePart);
        $this->assertInstanceOf(DataPart::class, $dataPart);

        // 5. Test Event system
        $eventBus = new ExecutionEventBusImpl();
        $executor = new DefaultAgentExecutor();
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

        // 6. Test Error codes
        $this->assertEquals(-32002, A2AErrorCodes::TASK_NOT_CANCELABLE);
        $this->assertEquals(-32003, A2AErrorCodes::PUSH_NOTIFICATION_NOT_SUPPORTED);

        $this->assertTrue(true); // All tests passed
    }
}
