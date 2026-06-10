<?php

declare(strict_types=1);

namespace A2A\Tests\Models;

use PHPUnit\Framework\TestCase;
use A2A\Models\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\AgentProvider;

class AgentCardV2Test extends TestCase
{
    public function testProtocolCompliantAgentCard(): void
    {
        $capabilities = new AgentCapabilities(true, true, true);
        $skill = new AgentSkill('test', 'Test Skill', 'Test description', ['test']);
        $provider = new AgentProvider('Test Org', 'https://test.com');

        $agentCard = new AgentCard(
            'Test Agent',
            'Test Description',
            'https://example.com/agent',
            '1.0.0',
            $capabilities,
            ['text'],
            ['text'],
            [$skill],
            '0.3.0'
        );
        $agentCard->setProvider($provider);

        $array = $agentCard->toArray();

        $this->assertEquals('Test Agent', $array['name']);
        $this->assertEquals('https://example.com/agent', $array['url']);
        $this->assertEquals('0.3.0', $array['protocolVersion']);
        $this->assertCount(1, $array['skills']);
        $this->assertTrue($array['capabilities']['streaming']);
        $this->assertEquals('Test Org', $array['provider']['organization']);
    }
}
