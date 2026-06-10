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
 * Negative-case corpus for the JSON-RPC 2.0 envelope (audit R9):
 * every malformed envelope must produce -32600 (Invalid Request) and
 * never raise a PHP warning or uncaught exception.
 */
class JsonRpcNegativeCorpusTest extends TestCase
{
    private A2AProtocol_v030 $protocol;

    protected function setUp(): void
    {
        $agentCard = new AgentCard(
            'corpus-agent',
            'Negative corpus agent',
            'https://example.com/agent',
            '1.0.0',
            new AgentCapabilities(),
            ['text/plain'],
            ['application/json'],
            [new AgentSkill('test', 'Test', 'Test skill', ['test'])]
        );

        $this->protocol = new A2AProtocol_v030(
            $agentCard,
            $this->createMock(HttpClient::class)
        );
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function malformedEnvelopes(): array
    {
        return [
            'empty request' => [[]],
            'missing jsonrpc' => [['method' => 'ping', 'id' => 1]],
            'wrong jsonrpc version string' => [['jsonrpc' => '1.0', 'method' => 'ping', 'id' => 1]],
            'jsonrpc as number' => [['jsonrpc' => 2.0, 'method' => 'ping', 'id' => 1]],
            'jsonrpc null' => [['jsonrpc' => null, 'method' => 'ping', 'id' => 1]],
            'missing method' => [['jsonrpc' => '2.0', 'id' => 1]],
            'method null' => [['jsonrpc' => '2.0', 'method' => null, 'id' => 1]],
            'method numeric' => [['jsonrpc' => '2.0', 'method' => 42, 'id' => 1]],
            'method empty string' => [['jsonrpc' => '2.0', 'method' => '', 'id' => 1]],
            'method whitespace' => [['jsonrpc' => '2.0', 'method' => '   ', 'id' => 1]],
            'method array' => [['jsonrpc' => '2.0', 'method' => ['ping'], 'id' => 1]],
            'id as bool' => [['jsonrpc' => '2.0', 'method' => 'ping', 'id' => true]],
            'id as float' => [['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1.5]],
            'id as array' => [['jsonrpc' => '2.0', 'method' => 'ping', 'id' => [1]]],
            'params as string' => [['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1, 'params' => 'oops']],
            'params as int' => [['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1, 'params' => 3]],
        ];
    }

    /**
     * @dataProvider malformedEnvelopes
     * @param array<string, mixed> $request
     */
    public function testMalformedEnvelopeReturnsInvalidRequest(array $request): void
    {
        $response = $this->protocol->handleRequest($request);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('error', $response);
        $this->assertSame(A2AErrorCodes::INVALID_REQUEST, $response['error']['code']);
    }

    public function testUnknownMethodReturnsMethodNotFound(): void
    {
        $response = $this->protocol->handleRequest([
            'jsonrpc' => '2.0',
            'method' => 'no/such/method',
            'id' => 99
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(A2AErrorCodes::METHOD_NOT_FOUND, $response['error']['code']);
    }

    public function testNotificationWithoutIdStillHandled(): void
    {
        $response = $this->protocol->handleRequest([
            'jsonrpc' => '2.0',
            'method' => 'ping'
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertNull($response['id']);
    }
}
