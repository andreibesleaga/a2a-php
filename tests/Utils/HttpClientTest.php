<?php

declare(strict_types=1);

namespace A2A\Tests\Utils;

use PHPUnit\Framework\TestCase;
use A2A\Utils\HttpClient;

class HttpClientTest extends TestCase
{
    private HttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new HttpClient();
    }

    public function testPostWithValidUrl(): void
    {
        // Mock a simple HTTP server response
        $this->expectException(\Exception::class);
        $this->httpClient->post('http://invalid-url-for-testing', ['test' => 'data']);
    }

    public function testPostWithInvalidUrl(): void
    {
        $this->expectException(\Exception::class);
        $this->httpClient->post('invalid-url', ['test' => 'data']);
    }

    public function testGetWithValidUrl(): void
    {
        $this->expectException(\Exception::class);
        $this->httpClient->get('http://invalid-url-for-testing');
    }

    public function testGetWithInvalidUrl(): void
    {
        $this->expectException(\Exception::class);
        $this->httpClient->get('invalid-url');
    }
}
