<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\A2AProtocol_v030;
use A2A\Exceptions\A2AErrorCodes;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\v030\AgentCard;
use A2A\Utils\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Protocol-level coverage for push notification webhook delivery
 * (A2A v0.3.0 §9.5) and the opt-in webhook allowlist (audit R9).
 */
class PushNotificationDeliveryTest extends TestCase
{
    /** @var HttpClient&\PHPUnit\Framework\MockObject\MockObject */
    private HttpClient $httpClient;
    private A2AProtocol_v030 $protocol;

    protected function setUp(): void
    {
        putenv('A2A_WEBHOOK_ALLOWLIST');

        $agentCard = new AgentCard(
            'push-agent',
            'Push delivery agent',
            'https://example.com/agent',
            '1.0.0',
            new AgentCapabilities(true, true),
            ['text/plain'],
            ['application/json'],
            [new AgentSkill('test', 'Test', 'Test skill', ['test'])]
        );

        $this->httpClient = $this->createMock(HttpClient::class);
        $this->protocol = new A2AProtocol_v030($agentCard, $this->httpClient);
    }

    protected function tearDown(): void
    {
        putenv('A2A_WEBHOOK_ALLOWLIST');
    }

    private function rpc(string $method, array $params, int $id = 1): array
    {
        return $this->protocol->handleRequest([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params
        ]);
    }

    private function createTaskWithConfig(string $webhookUrl, array $extraConfig = []): string
    {
        $create = $this->rpc('message/send', [
            'message' => [
                'kind' => 'message',
                'messageId' => 'msg-create',
                'role' => 'user',
                'parts' => [['kind' => 'text', 'text' => 'create task']]
            ]
        ]);
        $taskId = $create['result']['id'];

        $set = $this->rpc('tasks/pushNotificationConfig/set', [
            'taskId' => $taskId,
            'pushNotificationConfig' => array_merge(['url' => $webhookUrl], $extraConfig)
        ], 2);
        $this->assertArrayHasKey('result', $set);

        return $taskId;
    }

    public function testGoldenPathStateChangeDeliversWebhook(): void
    {
        $delivered = [];
        $this->httpClient->method('postNotification')
            ->willReturnCallback(function (string $url, array $payload, array $headers) use (&$delivered): bool {
                $delivered[] = [$url, $payload, $headers];
                return true;
            });

        $taskId = $this->createTaskWithConfig('https://hooks.example.com/cb', [
            'token' => 'notif-token',
            'authentication' => ['schemes' => ['Bearer'], 'credentials' => 'cred-1']
        ]);

        $trigger = $this->rpc('message/send', [
            'message' => [
                'kind' => 'message',
                'messageId' => 'msg-trigger',
                'role' => 'user',
                'taskId' => $taskId,
                'parts' => [['kind' => 'text', 'text' => 'complete it']]
            ]
        ], 3);

        $this->assertArrayHasKey('result', $trigger);
        $this->assertNotEmpty($delivered, 'state change must deliver at least one webhook');

        [$url, $payload, $headers] = $delivered[0];
        $this->assertSame('https://hooks.example.com/cb', $url);
        $this->assertSame('task', $payload['kind']);
        $this->assertSame($taskId, $payload['id']);
        $this->assertArrayHasKey('state', $payload['status']);
        $this->assertSame('notif-token', $headers['X-A2A-Notification-Token']);
        $this->assertSame('Bearer cred-1', $headers['Authorization']);
    }

    public function testCancelDeliversWebhook(): void
    {
        $calls = 0;
        $this->httpClient->method('postNotification')
            ->willReturnCallback(function () use (&$calls): bool {
                $calls++;
                return true;
            });

        // tasks/send leaves the task completed; create a fresh working task
        // via the task manager instead so cancellation is possible.
        $task = $this->protocol->getTaskManager()->createTask('cancelable', [], 'task-cancel-1');
        $set = $this->rpc('tasks/pushNotificationConfig/set', [
            'taskId' => $task->getId(),
            'pushNotificationConfig' => ['url' => 'https://hooks.example.com/cb']
        ]);
        $this->assertArrayHasKey('result', $set);

        $cancel = $this->rpc('tasks/cancel', ['id' => $task->getId()], 2);

        $this->assertArrayHasKey('result', $cancel);
        $this->assertSame('canceled', $cancel['result']['status']['state']);
        $this->assertGreaterThanOrEqual(1, $calls, 'cancellation must deliver a webhook');
    }

    public function testNoDeliveryWithoutConfig(): void
    {
        $this->httpClient->expects($this->never())->method('postNotification');

        $response = $this->rpc('message/send', [
            'message' => [
                'kind' => 'message',
                'messageId' => 'msg-noconfig',
                'role' => 'user',
                'parts' => [['kind' => 'text', 'text' => 'no webhook']]
            ]
        ]);

        $this->assertArrayHasKey('result', $response);
    }

    public function testDeliveryFailureDoesNotBreakResponse(): void
    {
        $this->httpClient->method('postNotification')
            ->willThrowException(new \A2A\Exceptions\A2AException('unreachable'));

        $taskId = $this->createTaskWithConfig('https://hooks.example.com/cb');

        $trigger = $this->rpc('message/send', [
            'message' => [
                'kind' => 'message',
                'messageId' => 'msg-fail',
                'role' => 'user',
                'taskId' => $taskId,
                'parts' => [['kind' => 'text', 'text' => 'still works']]
            ]
        ], 3);

        $this->assertArrayHasKey('result', $trigger);
        $this->assertSame($taskId, $trigger['result']['id']);
    }

    public function testAllowlistRejectsConfigForUnlistedHost(): void
    {
        putenv('A2A_WEBHOOK_ALLOWLIST=hooks.example.com');

        $create = $this->rpc('message/send', [
            'message' => [
                'kind' => 'message',
                'messageId' => 'msg-allow',
                'role' => 'user',
                'parts' => [['kind' => 'text', 'text' => 'task']]
            ]
        ]);
        $taskId = $create['result']['id'];

        $set = $this->rpc('tasks/pushNotificationConfig/set', [
            'taskId' => $taskId,
            'pushNotificationConfig' => ['url' => 'https://evil.example.org/exfil']
        ], 2);

        $this->assertArrayHasKey('error', $set);
        $this->assertSame(A2AErrorCodes::INVALID_PARAMS, $set['error']['code']);
    }

    public function testAllowlistAcceptsConfigForListedHost(): void
    {
        putenv('A2A_WEBHOOK_ALLOWLIST=hooks.example.com');

        $create = $this->rpc('message/send', [
            'message' => [
                'kind' => 'message',
                'messageId' => 'msg-allow-ok',
                'role' => 'user',
                'parts' => [['kind' => 'text', 'text' => 'task']]
            ]
        ]);
        $taskId = $create['result']['id'];

        $set = $this->rpc('tasks/pushNotificationConfig/set', [
            'taskId' => $taskId,
            'pushNotificationConfig' => ['url' => 'https://hooks.example.com/cb']
        ], 2);

        $this->assertArrayHasKey('result', $set);
    }

    public function testConfigWithoutUrlRejected(): void
    {
        $create = $this->rpc('message/send', [
            'message' => [
                'kind' => 'message',
                'messageId' => 'msg-nourl',
                'role' => 'user',
                'parts' => [['kind' => 'text', 'text' => 'task']]
            ]
        ]);
        $taskId = $create['result']['id'];

        $set = $this->rpc('tasks/pushNotificationConfig/set', [
            'taskId' => $taskId,
            'pushNotificationConfig' => ['token' => 'no-url-here']
        ], 2);

        $this->assertArrayHasKey('error', $set);
        $this->assertSame(A2AErrorCodes::INVALID_PARAMS, $set['error']['code']);
    }
}
