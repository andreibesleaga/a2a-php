<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\A2AProtocol_v030;
use A2A\Interfaces\MessageHandlerInterface;
use A2A\Models\TaskStatus;
use A2A\Models\TextPart;
use PHPUnit\Framework\TestCase;
use A2A\A2AServer;
use A2A\Models\v030\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\v030\Message;
use A2A\TaskManager;
use A2A\Models\TaskState;
use A2A\Utils\HttpClient;

class A2AServerTest extends TestCase
{
    private A2AServer $server;
    private AgentCard $agentCard;
    private TestLogger $logger;
    private TaskManager $taskManager;
    private A2AProtocol_v030 $protocol;

    protected function setUp(): void
    {
        $capabilities = new AgentCapabilities();
        $skill = new AgentSkill('test', 'Test', 'Test skill', ['test']);

        $this->agentCard = new AgentCard(
            'Server Agent',
            'Test server agent',
            'https://example.com/agent',
            '1.0.0',
            $capabilities,
            ['text/plain'],
            ['application/json'],
            [$skill]
        );

        $this->logger = new TestLogger();
        $this->taskManager = new TaskManager();
        $httpClient = $this->createMock(HttpClient::class);

        $this->protocol = new A2AProtocol_v030(
            $this->agentCard,
            $httpClient,
            $this->logger,
            $this->taskManager
        );

        $this->server = new A2AServer($this->protocol, $this->logger);
    }

    public function testHandleGetAgentCardRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'get_agent_card',
            'id' => 1
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertEquals('Server Agent', $response['result']['name']);
    }

    public function testHandlePingRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 2
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(2, $response['id']);
        $this->assertEquals('pong', $response['result']['status']);
    }

    public function testHandleMessageRequest(): void
    {
        $messageHandler = new class implements MessageHandlerInterface {
            public function canHandle(Message $message): bool
            {
                return true;
            }
            public function handle(Message $message, string $fromAgent): array
            {
                TestCase::assertEquals('Hello Server', $message->getParts()[0]->getText());
                TestCase::assertEquals('client-agent', $fromAgent);

                return [
                    'status' => ['state' => 'completed'],
                    'metadata' => [
                        'handled' => true,
                        'echo' => $message->getParts()[0]->getText()
                    ]
                ];
            }
        };
        $this->server->addMessageHandler($messageHandler);

        $message = Message::createUserMessage('Hello Server');
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'message/send',
            'params' => [
                'from' => 'client-agent',
                'message' => $message->toArray()
            ],
            'id' => 3
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(3, $response['id']);

        $result = $response['result'];
        $this->assertSame('task', $result['kind']);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('completed', $result['status']['state']);
        $this->assertSame('message/send', $result['metadata']['source']);
        $this->assertNotEmpty($result['history']);
        $this->assertTrue($this->logger->hasRecordThatContains('info', 'Message processed'));
            $this->assertTrue($result['metadata']['handled']);
            $this->assertSame('Hello Server', $result['metadata']['echo']);
    }

    public function testHandleInvalidRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'unknown_method',
            'id' => 4
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(4, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Unknown method', $response['error']['message']);
    }

    public function testMessageHandlerException(): void
    {
        $this->server->addMessageHandler(
            new class implements MessageHandlerInterface {
                public function canHandle(Message $message): bool
                {
                    return true;
                }
                public function handle(Message $message, string $fromAgent): array
                {
                    throw new \Exception('Handler error');
                }
            }
        );

        $message = Message::createUserMessage('Test');
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'message/send',
            'params' => [
                'from' => 'client-agent',
                'message' => $message->toArray()
            ],
            'id' => 5
        ];

        $response = $this->server->handleRequest($request);

        $result = $response['result'];
        $this->assertEquals('submitted', $result['status']['state']);
        $this->assertSame('message/send', $result['metadata']['source']);
        $this->assertTrue($this->logger->hasRecordThatContains('error', 'Message handler failed'));
    }

    public function testHandleInvalidJsonRpc(): void
    {
        $request = [
            'method' => 'test',
            'id' => 1
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testHandleEmptyRequest(): void
    {
        $response = $this->server->handleRequest([]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testMultipleMessageHandlers(): void
    {
        $handler1 = new class implements MessageHandlerInterface {
            public function canHandle(Message $message): bool
            {
                return true;
            }
            public function handle(Message $message, string $fromAgent): array
            {
                return [
                    'status' => ['state' => 'completed'],
                    'metadata' => ['handled_by' => 'handler1']
                ];
            }
        };

        $handler2 = new class implements MessageHandlerInterface {
            public function canHandle(Message $message): bool
            {
                return true;
            }
            public function handle(Message $message, string $fromAgent): array
            {
                TestCase::fail('Second handler should not be invoked when first matches.');
            }
        };

        $this->server->addMessageHandler($handler1);
        $this->server->addMessageHandler($handler2);

        $message = Message::createUserMessage('Test');
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'message/send',
            'params' => [
                'from' => 'client-agent',
                'message' => $message->toArray()
            ],
            'id' => 6
        ];

        $response = $this->server->handleRequest($request);

        $result = $response['result'];
        $this->assertEquals('completed', $result['status']['state']);
        $this->assertSame('handler1', $result['metadata']['handled_by']);
    }

    public function testHandleTasksSendRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'id' => 'test-task-123',
                'message' => Message::createUserMessage('Test task for A2A protocol')->toArray(),
                'metadata' => ['key' => 'value']
            ],
            'id' => 'test-tasks-send'
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-tasks-send', $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('test-task-123', $response['result']['id']);
        $this->assertEquals(TaskState::COMPLETED->value, $response['result']['status']['state']);
    }

    public function testHandleTasksSendWithInvalidTask(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'id' => 'test-task-123',
                'message' => 'not-a-message-object'
            ],
            'id' => 'test-tasks-send-invalid'
        ];

        $response = $this->server->handleRequest($request);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-tasks-send-invalid', $response['id']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testHandleTasksSendMissingTaskParameter(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [],
            'id' => 'test-tasks-send-missing'
        ];

        $response = $this->server->handleRequest($request);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-tasks-send-missing', $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Task ID and message are required', $response['error']['message']);
    }

    public function testHandleTasksSendInvalidParams(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => 'not-an-array',
            'id' => 8
        ];

        $response = $this->server->handleRequest($request);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testHandleTasksSendMissingId(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'message' => Message::createUserMessage('Task without ID')->toArray()
            ],
            'id' => 9
        ];

        $response = $this->server->handleRequest($request);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(9, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Task ID and message are required', $response['error']['message']);
    }

    public function testHandleTasksSendEmptyMessage(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'id' => 'empty-message-task',
                'message' => []
            ],
            'id' => 10
        ];

        $response = $this->server->handleRequest($request);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(10, $response['id']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testA2AComplianceModeTasksSend(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'id' => 'compliance-task-123',
                'message' => Message::createUserMessage('A2A compliance test task')->toArray(),
                'metadata' => ['contextId' => 'ctx-compliance-123']
            ],
            'id' => 'test-compliance-tasks-send'
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-compliance-tasks-send', $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('compliance-task-123', $response['result']['id']);
    }

    public function testTasksPersistenceBetweenMethods(): void
    {
        $sendRequest = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/send',
            'params' => [
                'id' => 'persistent-task-123',
                'message' => Message::createUserMessage('Task for persistence test')->toArray(),
                'metadata' => ['contextId' => 'ctx-persistent-123']
            ],
            'id' => 'test-send'
        ];

        $this->server->handleRequest($sendRequest);

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
