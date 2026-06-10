<?php

declare(strict_types=1);

namespace A2A\Tests;

use A2A\A2AProtocol_v030;
use A2A\Exceptions\A2AErrorCodes;
use A2A\Exceptions\InvalidRequestException;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\Part;
use A2A\Models\v030\AgentCard;
use A2A\Models\v030\Message;
use A2A\Utils\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Error-path coverage for required A2A Message fields (spec §5.1):
 * a Message MUST have messageId, role and parts, and violations MUST be
 * rejected with JSON-RPC -32602 (InvalidParams).
 */
class MessageValidationTest extends TestCase
{
    private A2AProtocol_v030 $protocol;

    protected function setUp(): void
    {
        $agentCard = new AgentCard(
            'validation-agent',
            'Validation test agent',
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

    private function send(array $message): array
    {
        return $this->protocol->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'message/send',
            'params' => ['message' => $message]
        ]);
    }

    public function testGoldenPathValidMessageCreatesTask(): void
    {
        $response = $this->send([
            'kind' => 'message',
            'messageId' => 'msg-1',
            'role' => 'user',
            'parts' => [['kind' => 'text', 'text' => 'hello']]
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertSame('task', $response['result']['kind']);
        $this->assertArrayHasKey('status', $response['result']);
    }

    public function testMissingMessageIdRejectedWithInvalidParams(): void
    {
        $response = $this->send([
            'kind' => 'message',
            'role' => 'user',
            'parts' => [['kind' => 'text', 'text' => 'no id']]
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(A2AErrorCodes::INVALID_PARAMS, $response['error']['code']);
    }

    public function testEmptyMessageIdRejectedWithInvalidParams(): void
    {
        $response = $this->send([
            'kind' => 'message',
            'messageId' => '',
            'role' => 'user',
            'parts' => [['kind' => 'text', 'text' => 'empty id']]
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(A2AErrorCodes::INVALID_PARAMS, $response['error']['code']);
    }

    public function testMissingRoleRejectedWithInvalidParams(): void
    {
        $response = $this->send([
            'kind' => 'message',
            'messageId' => 'msg-2',
            'parts' => [['kind' => 'text', 'text' => 'no role']]
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(A2AErrorCodes::INVALID_PARAMS, $response['error']['code']);
    }

    public function testMissingPartsRejectedWithInvalidParams(): void
    {
        $response = $this->send([
            'kind' => 'message',
            'messageId' => 'msg-3',
            'role' => 'user'
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(A2AErrorCodes::INVALID_PARAMS, $response['error']['code']);
    }

    public function testNonArrayMessageRejectedWithInvalidParams(): void
    {
        $response = $this->protocol->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'message/send',
            'params' => ['message' => 'just a string']
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(A2AErrorCodes::INVALID_PARAMS, $response['error']['code']);
    }

    public function testFromArrayThrowsTypedExceptionForMissingMessageId(): void
    {
        $this->expectException(InvalidRequestException::class);

        Message::fromArray([
            'role' => 'user',
            'parts' => [['kind' => 'text', 'text' => 'x']]
        ]);
    }

    public function testFromArrayThrowsTypedExceptionForMissingParts(): void
    {
        $this->expectException(InvalidRequestException::class);

        Message::fromArray([
            'messageId' => 'msg-4',
            'role' => 'user'
        ]);
    }

    public function testFilePartWithoutFileObjectRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Part::fromArray(['kind' => 'file']);
    }

    public function testDataPartWithoutDataFieldRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Part::fromArray(['kind' => 'data']);
    }

    public function testUnknownPartKindRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Part::fromArray(['kind' => 'hologram']);
    }
}
