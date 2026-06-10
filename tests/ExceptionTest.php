<?php

declare(strict_types=1);

namespace A2A\Tests;

use PHPUnit\Framework\TestCase;
use A2A\Exceptions\A2AException;
use A2A\Exceptions\InvalidRequestException;
use A2A\Exceptions\TaskNotFoundException;
use A2A\Exceptions\A2AErrorCodes;

class ExceptionTest extends TestCase
{
    public function testA2AException(): void
    {
        $exception = new A2AException('Test error message');
        $this->assertEquals('Test error message', $exception->getMessage());
    }

    public function testInvalidRequestException(): void
    {
        $exception = new InvalidRequestException('Invalid request');
        $this->assertEquals('Invalid request', $exception->getMessage());
    }

    public function testTaskNotFoundException(): void
    {
        $exception = new TaskNotFoundException('Task not found');
        $this->assertEquals('Task not found', $exception->getMessage());
    }

    public function testA2AErrorCodes(): void
    {
        $this->assertEquals(-32600, A2AErrorCodes::INVALID_REQUEST);
        $this->assertEquals(-32601, A2AErrorCodes::METHOD_NOT_FOUND);
        $this->assertEquals(-32602, A2AErrorCodes::INVALID_PARAMS);
        $this->assertEquals(-32603, A2AErrorCodes::INTERNAL_ERROR);
        $this->assertEquals(-32001, A2AErrorCodes::TASK_NOT_FOUND);
        $this->assertEquals(-32002, A2AErrorCodes::TASK_NOT_CANCELABLE);
        $this->assertEquals(-32006, A2AErrorCodes::INVALID_AGENT_RESPONSE);
    }
}
