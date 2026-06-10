<?php

declare(strict_types=1);

namespace A2A\Tests\E2E;

use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests against the real reference server
 * (examples/complete_a2a_server.php) running under PHP's built-in web
 * server, plus a separate webhook receiver process.
 *
 * Covers the documented golden path (README "Quick start"), protocol error
 * paths over real HTTP, SSE streaming, and push notification webhook
 * delivery — the same behaviors the official a2a-tck validates.
 */
class CompleteServerE2ETest extends TestCase
{
    private static ?int $serverPort = null;
    private static ?int $webhookPort = null;
    /** @var resource|null */
    private static $serverProc = null;
    /** @var resource|null */
    private static $webhookProc = null;
    private static string $webhookLog = '';

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open is not available');
        }

        self::$webhookLog = tempnam(sys_get_temp_dir(), 'a2a_hooks_') . '.jsonl';

        self::$serverPort = self::findFreePort();
        self::$webhookPort = self::findFreePort();

        $root = dirname(__DIR__, 2);

        self::$serverProc = self::startServer(
            self::$serverPort,
            $root . '/examples/complete_a2a_server.php'
        );
        self::$webhookProc = self::startServer(
            self::$webhookPort,
            __DIR__ . '/fixtures/webhook_receiver.php',
            ['A2A_TEST_WEBHOOK_LOG' => self::$webhookLog]
        );

        self::waitForServer(self::$serverPort);
        self::waitForServer(self::$webhookPort, '/', true);
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::$serverProc, self::$webhookProc] as $proc) {
            if (is_resource($proc)) {
                $status = proc_get_status($proc);
                if ($status['running'] && $status['pid']) {
                    // Kill the process group: php -S may have children.
                    posix_kill($status['pid'], 15);
                }
                proc_terminate($proc);
                proc_close($proc);
            }
        }

        if (self::$webhookLog !== '' && file_exists(self::$webhookLog)) {
            unlink(self::$webhookLog);
        }
    }

    private static function findFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($sock, false);
        $port = (int) substr($name, strrpos($name, ':') + 1);
        fclose($sock);
        return $port;
    }

    /** @return resource */
    private static function startServer(int $port, string $router, array $env = [])
    {
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router];
        $proc = proc_open(
            $cmd,
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            dirname($router),
            array_merge(getenv(), $env)
        );

        if (!is_resource($proc)) {
            self::fail('Could not start PHP built-in server on port ' . $port);
        }

        return $proc;
    }

    private static function waitForServer(int $port, string $path = '/', bool $any = false): void
    {
        $deadline = microtime(true) + 10.0;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.25);
            if (is_resource($conn)) {
                fclose($conn);
                return;
            }
            usleep(100_000);
        }
        self::fail('Server on port ' . $port . ' did not come up within 10s');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private function httpRequest(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        $url = 'http://127.0.0.1:' . self::$serverPort . $path;

        $headerLines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => 15,
            ]
        ]);

        $responseBody = file_get_contents($url, false, $context);
        $this->assertNotFalse($responseBody, 'HTTP request to ' . $path . ' failed entirely');

        $status = 0;
        $responseHeaders = [];
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int) $m[1];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $responseBody];
    }

    private function rpc(string $method, array $params = [], mixed $id = 1): array
    {
        $payload = ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method];
        if ($params !== []) {
            $payload['params'] = $params;
        }

        $response = $this->httpRequest('POST', '/', json_encode($payload));
        $decoded = json_decode($response['body'], true);

        $this->assertIsArray($decoded, 'Response is not valid JSON: ' . substr($response['body'], 0, 200));
        $this->assertSame('2.0', $decoded['jsonrpc'] ?? null);

        return $decoded;
    }

    private function textMessage(string $text, string $messageId, ?string $taskId = null): array
    {
        $message = [
            'kind' => 'message',
            'messageId' => $messageId,
            'role' => 'user',
            'parts' => [['kind' => 'text', 'text' => $text]]
        ];
        if ($taskId !== null) {
            $message['taskId'] = $taskId;
        }
        return ['message' => $message];
    }

    public function testWellKnownAgentCardIsServed(): void
    {
        $response = $this->httpRequest('GET', '/.well-known/agent-card.json');

        $this->assertSame(200, $response['status']);
        $card = json_decode($response['body'], true);
        $this->assertIsArray($card);
        $this->assertSame('0.3.0', $card['protocolVersion']);
        $this->assertTrue($card['capabilities']['streaming']);
        $this->assertTrue($card['capabilities']['pushNotifications']);
        $this->assertNotEmpty($card['skills']);
    }

    public function testPingGoldenPath(): void
    {
        $response = $this->rpc('ping');

        $this->assertSame(['status' => 'pong'], $response['result']);
    }

    public function testMessageSendGoldenPathCreatesTask(): void
    {
        $response = $this->rpc('message/send', $this->textMessage('hello e2e', 'e2e-msg-1'));

        $this->assertArrayHasKey('result', $response);
        $this->assertSame('task', $response['result']['kind']);
        $this->assertArrayHasKey('id', $response['result']);
        $this->assertContains(
            $response['result']['status']['state'],
            ['submitted', 'working', 'completed']
        );
    }

    public function testTaskLifecycleGetHistoryAndIdempotentCancel(): void
    {
        $created = $this->rpc('message/send', $this->textMessage('lifecycle', 'e2e-msg-2'));
        $taskId = $created['result']['id'];

        $got = $this->rpc('tasks/get', ['id' => $taskId, 'historyLength' => 1], 2);
        $this->assertSame($taskId, $got['result']['id']);
        $this->assertLessThanOrEqual(1, count($got['result']['history'] ?? []));

        // Second message completes the task (reference server behavior).
        $this->rpc('message/send', $this->textMessage('finish', 'e2e-msg-3', $taskId), 3);

        $cancel = $this->rpc('tasks/cancel', ['id' => $taskId], 4);
        $this->assertArrayHasKey('error', $cancel, 'completed task must not be cancelable');
        $this->assertSame(-32002, $cancel['error']['code']);
    }

    public function testErrorPathTaskNotFound(): void
    {
        $response = $this->rpc('tasks/get', ['id' => 'no-such-task-id'], 5);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32001, $response['error']['code']);
    }

    public function testErrorPathMalformedEnvelope(): void
    {
        $response = $this->rpc('ping', [], 6);
        $this->assertArrayHasKey('result', $response); // control

        $raw = $this->httpRequest('POST', '/', json_encode(['method' => 'ping', 'id' => 7]));
        $decoded = json_decode($raw['body'], true);

        $this->assertSame(-32600, $decoded['error']['code']);
    }

    public function testErrorPathParseError(): void
    {
        $raw = $this->httpRequest('POST', '/', '{not json');
        $decoded = json_decode($raw['body'], true);

        $this->assertSame(-32700, $decoded['error']['code']);
    }

    public function testErrorPathUnknownMethod(): void
    {
        $response = $this->rpc('definitely/not/a/method', [], 8);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32601, $response['error']['code']);
    }

    public function testErrorPathMissingMessageIdOverHttp(): void
    {
        $response = $this->rpc('message/send', [
            'message' => [
                'kind' => 'message',
                'role' => 'user',
                'parts' => [['kind' => 'text', 'text' => 'no id']]
            ]
        ], 9);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32602, $response['error']['code']);
    }

    public function testResponseBodyContainsNoPhpWarnings(): void
    {
        $raw = $this->httpRequest('POST', '/', json_encode([
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'message/send',
            'params' => [
                'message' => [
                    'kind' => 'message',
                    'role' => 'user',
                    'parts' => [['kind' => 'text', 'text' => 'warning probe']]
                ]
            ]
        ]));

        $this->assertStringStartsWith('{', trim($raw['body']), 'response must be pure JSON');
        $this->assertStringNotContainsString('Warning', $raw['body']);
        $this->assertStringNotContainsString('<br', $raw['body']);
    }

    public function testStreamingEndpointEmitsServerSentEvents(): void
    {
        $raw = $this->httpRequest('POST', '/', json_encode([
            'jsonrpc' => '2.0',
            'id' => 'stream-1',
            'method' => 'message/stream',
            'params' => $this->textMessage('stream me', 'e2e-stream-1')
        ]));

        $this->assertStringContainsString('text/event-stream', $raw['headers']['content-type'] ?? '');
        $this->assertStringContainsString('data:', $raw['body']);
    }

    public function testPushNotificationWebhookDeliveryEndToEnd(): void
    {
        @unlink(self::$webhookLog);

        $created = $this->rpc('message/send', $this->textMessage('push me', 'e2e-push-1'), 11);
        $taskId = $created['result']['id'];

        $webhookUrl = 'http://127.0.0.1:' . self::$webhookPort . '/hook';
        $set = $this->rpc('tasks/pushNotificationConfig/set', [
            'taskId' => $taskId,
            'pushNotificationConfig' => [
                'url' => $webhookUrl,
                'token' => 'e2e-notification-token',
                'authentication' => ['schemes' => ['Bearer'], 'credentials' => 'e2e-cred']
            ]
        ], 12);
        $this->assertArrayHasKey('result', $set);

        $get = $this->rpc('tasks/pushNotificationConfig/get', ['id' => $taskId], 13);
        $this->assertSame($webhookUrl, $get['result']['pushNotificationConfig']['url']);

        // Trigger a state change; the server must deliver the webhook.
        $this->rpc('message/send', $this->textMessage('trigger push', 'e2e-push-2', $taskId), 14);

        $entry = $this->waitForWebhookEntry();
        $this->assertNotNull($entry, 'webhook receiver got no delivery within timeout');

        $this->assertSame('POST', $entry['method']);
        $this->assertSame('e2e-notification-token', $entry['headers']['x-a2a-notification-token'] ?? null);
        $this->assertSame('Bearer e2e-cred', $entry['headers']['authorization'] ?? null);

        $payload = json_decode($entry['body'], true);
        $this->assertIsArray($payload);
        $this->assertSame('task', $payload['kind']);
        $this->assertSame($taskId, $payload['id']);
        $this->assertArrayHasKey('state', $payload['status']);

        $delete = $this->rpc('tasks/pushNotificationConfig/delete', ['id' => $taskId], 15);
        $this->assertArrayHasKey('result', $delete);
    }

    private function waitForWebhookEntry(float $timeout = 10.0): ?array
    {
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            if (file_exists(self::$webhookLog)) {
                $lines = array_filter(explode("\n", (string) file_get_contents(self::$webhookLog)));
                if ($lines !== []) {
                    return json_decode((string) end($lines), true);
                }
            }
            usleep(200_000);
        }
        return null;
    }
}
