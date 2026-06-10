<?php

declare(strict_types=1);

namespace A2A\Tests\Notifications;

use A2A\Exceptions\A2AException;
use A2A\Models\PushNotificationConfig;
use A2A\Models\PushNotificationAuthenticationInfo;
use A2A\Models\TaskState;
use A2A\Models\TaskStatus;
use A2A\Models\v030\Task;
use A2A\Notifications\PushNotifier;
use A2A\PushNotificationManager;
use A2A\Utils\HttpClient;
use PHPUnit\Framework\TestCase;

class PushNotifierTest extends TestCase
{
    private PushNotificationManager $manager;

    protected function setUp(): void
    {
        // Array storage keeps each test isolated; the default file driver
        // persists configs in /tmp across test runs.
        $this->manager = new PushNotificationManager(new \A2A\Storage\Storage('array'));
        putenv('A2A_WEBHOOK_ALLOWLIST');
    }

    protected function tearDown(): void
    {
        putenv('A2A_WEBHOOK_ALLOWLIST');
    }

    private function makeTask(string $id = 'task-1'): Task
    {
        return new Task($id, 'ctx-1', new TaskStatus(TaskState::COMPLETED));
    }

    public function testGoldenPathDeliversTaskPayloadWithTokenAndAuthHeaders(): void
    {
        $config = new PushNotificationConfig(
            'https://hooks.example.com/notify',
            null,
            'secret-token',
            new PushNotificationAuthenticationInfo(['Bearer'], 'credential-xyz')
        );
        $this->manager->setConfig('task-1', $config);

        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->once())
            ->method('postNotification')
            ->with(
                'https://hooks.example.com/notify',
                $this->callback(function (array $payload): bool {
                    return $payload['kind'] === 'task'
                        && $payload['id'] === 'task-1'
                        && isset($payload['status']['state']);
                }),
                $this->callback(function (array $headers): bool {
                    return ($headers['X-A2A-Notification-Token'] ?? null) === 'secret-token'
                        && ($headers['Authorization'] ?? null) === 'Bearer credential-xyz';
                })
            )
            ->willReturn(true);

        $notifier = new PushNotifier($this->manager, $httpClient);
        $notifier->notify($this->makeTask());
    }

    public function testNoConfigMeansNoDelivery(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->never())->method('postNotification');

        $notifier = new PushNotifier($this->manager, $httpClient);
        $notifier->notify($this->makeTask());
    }

    public function testDeliveryFailureIsSwallowed(): void
    {
        $this->manager->setConfig('task-1', new PushNotificationConfig('https://hooks.example.com/notify'));

        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->method('postNotification')
            ->willThrowException(new A2AException('connection refused'));

        $notifier = new PushNotifier($this->manager, $httpClient);

        // Must not propagate: webhook delivery is best-effort.
        $notifier->notify($this->makeTask());
        $this->addToAssertionCount(1);
    }

    public function testAllowlistBlocksDeliveryToUnlistedHost(): void
    {
        putenv('A2A_WEBHOOK_ALLOWLIST=hooks.example.com');
        $this->manager->setConfig('task-1', new PushNotificationConfig('https://evil.example.org/exfil'));

        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->never())->method('postNotification');

        $notifier = new PushNotifier($this->manager, $httpClient);
        $notifier->notify($this->makeTask());
    }

    public function testAllowlistPermitsListedHost(): void
    {
        putenv('A2A_WEBHOOK_ALLOWLIST=other.example.com, hooks.example.com');
        $this->manager->setConfig('task-1', new PushNotificationConfig('https://hooks.example.com/notify'));

        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->once())->method('postNotification')->willReturn(true);

        $notifier = new PushNotifier($this->manager, $httpClient);
        $notifier->notify($this->makeTask());
    }

    public function testUnsetAllowlistAllowsEveryHost(): void
    {
        $notifier = new PushNotifier($this->manager, $this->createMock(HttpClient::class));

        $this->assertTrue($notifier->isWebhookUrlAllowed('https://anywhere.example.net/hook'));
    }

    public function testMalformedUrlIsRejectedWhenAllowlistActive(): void
    {
        putenv('A2A_WEBHOOK_ALLOWLIST=hooks.example.com');
        $notifier = new PushNotifier($this->manager, $this->createMock(HttpClient::class));

        $this->assertFalse($notifier->isWebhookUrlAllowed('not a url'));
    }
}
