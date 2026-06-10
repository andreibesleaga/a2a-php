<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\A2AProtocol_v030;
use A2A\A2AServer;
use A2A\TaskManager;
use A2A\Models\v030\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\PushNotificationConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use A2A\Storage\Storage;

class PushNotificationConfigTest extends TestCase
{
    private A2AServer $server;
    private TaskManager $taskManager;

    protected function setUp(): void
    {
        $capabilities = new AgentCapabilities(
            streaming: true,
            pushNotifications: true
        );

        $agentCard = new AgentCard(
            'Test Agent',
            'A test agent',
            'https://example.com/agent',
            '1.0.0',
            $capabilities,
            ['text/plain'],
            ['application/json'],
            []
        );

        $storage = new Storage('array');
        $this->taskManager = new TaskManager($storage);
        $protocol = new A2AProtocol_v030($agentCard, null, new NullLogger(), $this->taskManager);
        $this->server = new A2AServer($protocol, new NullLogger());
    }

    public function testSetPushNotificationConfig(): void
    {
        // First create a task
        $task = $this->taskManager->createTask('Test task for push config', ['test' => true]);

        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/set',
            'params' => [
                'taskId' => $task->getId(),
                'pushNotificationConfig' => [
                    'url' => 'https://example.com/webhook',
                    'id' => 'webhook-id',
                    'token' => 'secret-token',
                    'authentication' => ['schemes' => ['bearer']]
                ]
            ],
            'id' => 1
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals($task->getId(), $response['result']['taskId']);
    }

    public function testGetPushNotificationConfig(): void
    {
        // First create a task
        $task = $this->taskManager->createTask('Test task for push config get', ['test' => true]);

        // First set a config
        $setRequest = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/set',
            'params' => [
                'taskId' => $task->getId(),
                'pushNotificationConfig' => [
                    'url' => 'https://example.com/webhook2',
                    'id' => 'webhook-id-2',
                    'token' => 'secret-token-2'
                ]
            ],
            'id' => 1
        ];
        $this->server->handleRequest($setRequest);

        // Now get it
        $getRequest = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/get',
            'params' => [
                'id' => $task->getId()
            ],
            'id' => 2
        ];

        $response = $this->server->handleRequest($getRequest);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(2, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('pushNotificationConfig', $response['result']);
    }

    public function testGetPushNotificationConfigNotFound(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/get',
            'params' => [
                'id' => 'non-existent-task'
            ],
            'id' => 3
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(3, $response['id']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testListPushNotificationConfigs(): void
    {
        // Create tasks first
        $task1 = $this->taskManager->createTask('Test task 1', ['test' => true]);
        $task2 = $this->taskManager->createTask('Test task 2', ['test' => true]);

        // Set a couple of configs
        $config1 = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/set',
            'params' => [
                'taskId' => $task1->getId(),
                'pushNotificationConfig' => ['url' => 'https://example.com/webhook1']
            ],
            'id' => 1
        ];
        $config2 = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/set',
            'params' => [
                'taskId' => $task2->getId(),
                'pushNotificationConfig' => ['url' => 'https://example.com/webhook2']
            ],
            'id' => 2
        ];
        $this->server->handleRequest($config1);
        $this->server->handleRequest($config2);

        // List them for task1
        $listRequest = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/list',
            'params' => [],
            'id' => 3
        ];

        $response = $this->server->handleRequest($listRequest);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(3, $response['id']);
        $this->assertArrayHasKey('result', $response);
    }

    public function testDeletePushNotificationConfig(): void
    {
        // First create a task
        $task = $this->taskManager->createTask('Test task for delete', ['test' => true]);

        // First set a config
        $setRequest = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/set',
            'params' => [
                'taskId' => $task->getId(),
                'pushNotificationConfig' => ['url' => 'https://example.com/webhook-delete']
            ],
            'id' => 1
        ];
        $this->server->handleRequest($setRequest);

        // Delete it
        $deleteRequest = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/delete',
            'params' => [
                'id' => $task->getId()
            ],
            'id' => 2
        ];

        $response = $this->server->handleRequest($deleteRequest);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(2, $response['id']);
        $this->assertNull($response['result']);
    }

    public function testDeletePushNotificationConfigNotFound(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/delete',
            'params' => [
                'id' => 'non-existent-task'
            ],
            'id' => 4
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(4, $response['id']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testTasksResubscribe(): void
    {
        // First create a task
        $task = $this->taskManager->createTask('Test resubscribe task', ['test' => true]);

        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/resubscribe',
            'params' => [
                'id' => $task->getId()
            ],
            'id' => 5
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(5, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('resubscribed', $response['result']['status']);
        $this->assertEquals($task->getId(), $response['result']['taskId']);
    }

    public function testTasksResubscribeNotFound(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/resubscribe',
            'params' => [
                'id' => 'non-existent-task'
            ],
            'id' => 6
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(6, $response['id']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testPushNotificationConfigMissingParameters(): void
    {
        // Test missing taskId
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/set',
            'params' => [
                'pushNotificationConfig' => ['url' => 'https://example.com/webhook']
            ],
            'id' => 7
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(7, $response['id']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testPushNotificationConfigInvalidConfig(): void
    {
        // First create a task
        $task = $this->taskManager->createTask('Test task for invalid config', ['test' => true]);

        // Test missing config
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tasks/pushNotificationConfig/set',
            'params' => [
                'taskId' => $task->getId()
            ],
            'id' => 8
        ];

        $response = $this->server->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(8, $response['id']);
        $this->assertArrayHasKey('error', $response);
    }
}
