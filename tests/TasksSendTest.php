<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\A2AProtocol_v030;
use A2A\A2AServer;
use A2A\Models\v030\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\v030\Message;
use A2A\TaskManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use A2A\Storage\Storage;

class TasksSendTest extends TestCase
{
    private A2AServer $server;
    private AgentCard $agentCard;
    private LoggerInterface $logger;
    private TaskManager $taskManager;

    protected function setUp(): void
    {
        $capabilities = new AgentCapabilities(true, true);
        $this->agentCard = new AgentCard(
            'Test Agent',
            'A test agent for unit testing',
            'http://localhost:8080',
            '1.0.0',
            $capabilities,
            ['text/plain'],
            ['application/json'],
            []
        );

        $this->logger = new NullLogger();
        $this->taskManager = new TaskManager(new Storage('array'));
        $protocol = new A2AProtocol_v030($this->agentCard, null, $this->logger, $this->taskManager);
        $this->server = new A2AServer($protocol, $this->logger);
    }

    public function testHandleTasksSendRequest(): void
    {
        $message = Message::createUserMessage('Test task for A2A protocol');
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'id' => 'test-task-123',
                'message' => $message->toArray(),
                'metadata' => ['key' => 'value']
            ],
            'id' => 'test-tasks-send'
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-tasks-send', $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('test-task-123', $response['result']['id']);
    }

    public function testHandleTasksSendWithInvalidMessage(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'id' => 'test-task-123',
                'message' => 'not-a-valid-message-object'
            ],
            'id' => 'test-tasks-send-invalid'
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-tasks-send-invalid', $response['id']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testHandleTasksSendMissingMessageParameter(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'id' => 'test-task-123'
            ],
            'id' => 'test-tasks-send-missing'
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-tasks-send-missing', $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Task ID and message are required', $response['error']['message']);
    }

    public function testTasksPersistenceBetweenMethods(): void
    {
        // First create a task via tasks/send
        $message = Message::createUserMessage('Task for persistence test');
        $sendRequest = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'id' => 'persistent-task-123',
                'message' => $message->toArray()
            ],
            'id' => 'test-send'
        ];

        $this->server->handleRequest($sendRequest);

        // Then retrieve it via tasks/get
        $getRequest = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/get',
            'params' => ['id' => 'persistent-task-123'],
            'id' => 'test-get'
        ];

        $response = $this->server->handleRequest($getRequest);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-get', $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('persistent-task-123', $response['result']['id']);
    }
}
