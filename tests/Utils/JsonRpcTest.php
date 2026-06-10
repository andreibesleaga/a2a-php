<?php

declare(strict_types=1);

namespace A2A\Tests\Utils;

use PHPUnit\Framework\TestCase;
use A2A\Utils\JsonRpc;
use A2A\Exceptions\InvalidRequestException;

class JsonRpcTest extends TestCase
{
    private JsonRpc $jsonRpc;

    protected function setUp(): void
    {
        $this->jsonRpc = new JsonRpc();
    }

    public function testCreateRequest(): void
    {
        $request = $this->jsonRpc->createRequest('test_method', ['param1' => 'value1'], 123);

        $expected = [
            'jsonrpc' => '2.0',
            'method' => 'test_method',
            'params' => ['param1' => 'value1'],
            'id' => 123
        ];

        $this->assertEquals($expected, $request);
    }

    public function testCreateRequestWithoutId(): void
    {
        $request = $this->jsonRpc->createRequest('test_method', ['param1' => 'value1']);

        $expected = [
            'jsonrpc' => '2.0',
            'method' => 'test_method',
            'params' => ['param1' => 'value1']
        ];

        $this->assertEquals($expected, $request);
    }

    public function testCreateResponse(): void
    {
        $response = $this->jsonRpc->createResponse(456, ['result' => 'success']);

        $expected = [
            'jsonrpc' => '2.0',
            'id' => 456,
            'result' => ['result' => 'success']
        ];

        $this->assertEquals($expected, $response);
    }

    public function testCreateError(): void
    {
        $error = $this->jsonRpc->createError(789, 'Something went wrong', -32000);

        $expected = [
            'jsonrpc' => '2.0',
            'id' => 789,
            'error' => [
                'code' => -32000,
                'message' => 'Something went wrong'
            ]
        ];

        $this->assertEquals($expected, $error);
    }

    public function testParseValidRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'params' => ['data' => 'test'],
            'id' => 1
        ];

        $parsed = $this->jsonRpc->parseRequest($request);

        $expected = [
            'method' => 'ping',
            'params' => ['data' => 'test'],
            'id' => 1
        ];

        $this->assertEquals($expected, $parsed);
    }

    public function testParseInvalidRequestMissingVersion(): void
    {
        $request = [
            'method' => 'ping',
            'id' => 1
        ];

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid JSON-RPC version');

        $this->jsonRpc->parseRequest($request);
    }

    public function testParseInvalidRequestMissingMethod(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1
        ];

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Missing method');

        $this->jsonRpc->parseRequest($request);
    }

    public function testIsValidRequest(): void
    {
        $validRequest = [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1
        ];

        $invalidRequest = [
            'method' => 'ping'
        ];

        $this->assertTrue($this->jsonRpc->isValidRequest($validRequest));
        $this->assertFalse($this->jsonRpc->isValidRequest($invalidRequest));
    }

    public function testCreateResponseWithNullId(): void
    {
        $response = $this->jsonRpc->createResponse(null, ['result' => 'success']);

        $expected = [
            'jsonrpc' => '2.0',
            'id' => null,
            'result' => ['result' => 'success']
        ];

        $this->assertEquals($expected, $response);
    }

    public function testCreateErrorWithNullId(): void
    {
        $error = $this->jsonRpc->createError(null, 'Something went wrong', -32000);

        $expected = [
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32000,
                'message' => 'Something went wrong'
            ]
        ];

        $this->assertEquals($expected, $error);
    }
}
