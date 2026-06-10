<?php

declare(strict_types=1);

namespace A2A\Tests\Handlers;

use A2A\Handlers\v030\HandlerRegistry;
use A2A\Handlers\v030\PingHandler;
use A2A\Handlers\v030\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;

class HandlerRegistryTest extends TestCase
{
    public function testRegisterAndGet(): void
    {
        $registry = new HandlerRegistry();
        $handler = new PingHandler();

        $registry->register('ping', $handler);

        $this->assertTrue($registry->has('ping'));
        $this->assertSame($handler, $registry->get('ping'));
        $this->assertSame(['ping'], $registry->methods());
    }

    public function testUnknownMethodReturnsNull(): void
    {
        $registry = new HandlerRegistry();

        $this->assertFalse($registry->has('nope'));
        $this->assertNull($registry->get('nope'));
    }

    public function testLastRegistrationWins(): void
    {
        $registry = new HandlerRegistry();
        $first = new PingHandler();
        $second = new class implements RequestHandlerInterface {
            public function handle(array $params, mixed $requestId): array
            {
                return [];
            }
        };

        $registry->register('ping', $first);
        $registry->register('ping', $second);

        $this->assertSame($second, $registry->get('ping'));
        $this->assertCount(1, $registry->methods());
    }
}
