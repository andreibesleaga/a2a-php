<?php

declare(strict_types=1);

namespace A2A\Tests\Models;

use PHPUnit\Framework\TestCase;
use A2A\Models\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;
use A2A\Models\AgentProvider;

class AgentCardTest extends TestCase
{
    public function testCreateAgentCard(): void
    {
        $capabilities = new AgentCapabilities();
        $skill = new AgentSkill('test', 'Test', 'Test skill', ['test']);

        $card = new AgentCard(
            'Test Agent',
            'A test agent',
            'https://example.com/agent',
            '2.0.0',
            $capabilities,
            ['text'],
            ['text'],
            [$skill]
        );

        $this->assertEquals('Test Agent', $card->getName());
        $this->assertEquals('A test agent', $card->getDescription());
        $this->assertEquals('2.0.0', $card->getVersion());
        $this->assertCount(1, $card->getSkills());
    }

    public function testToArray(): void
    {
        $capabilities = new AgentCapabilities();
        $skill = new AgentSkill('test', 'Test', 'Test skill', ['test']);

        $card = new AgentCard(
            'Test Agent',
            'Description',
            'https://example.com/agent',
            '1.0.0',
            $capabilities,
            ['text'],
            ['text'],
            [$skill]
        );

        $expected = [
            'name' => 'Test Agent',
            'description' => 'Description',
            'url' => 'https://example.com/agent',
            'version' => '1.0.0',
            'protocolVersion' => '0.3.0',
            'capabilities' => $capabilities->toArray(),
            'defaultInputModes' => ['text'],
            'defaultOutputModes' => ['text'],
            'skills' => [$skill->toArray()],
            'supportsAuthenticatedExtendedCard' => false,
            'preferredTransport' => 'JSONRPC',
        ];

        $this->assertEquals($expected, $card->toArray());
    }

    public function testFromArray(): void
    {
        $data = [
            'name' => 'Another Agent',
            'description' => 'Another description',
            'url' => 'https://example.com/agent',
            'version' => '2.1.0',
            'capabilities' => [],
            'defaultInputModes' => ['text'],
            'defaultOutputModes' => ['text'],
            'skills' => [
                ['id' => 'test', 'name' => 'Test', 'description' => 'Test skill', 'tags' => ['test']]
            ]
        ];

        $card = AgentCard::fromArray($data);

        $this->assertEquals('Another Agent', $card->getName());
        $this->assertEquals('Another description', $card->getDescription());
        $this->assertEquals('2.1.0', $card->getVersion());
        $this->assertCount(1, $card->getSkills());
    }

    public function testSetProvider(): void
    {
        $capabilities = new AgentCapabilities();
        $skill = new AgentSkill('test', 'Test', 'Test skill', ['test']);

        $card = new AgentCard(
            'Test Agent',
            'Description',
            'https://example.com/agent',
            '1.0.0',
            $capabilities,
            ['text'],
            ['text'],
            [$skill]
        );

        $provider = new AgentProvider('Test Org', 'https://test.com');
        $card->setProvider($provider);

        $this->assertEquals($provider, $card->getProvider());
    }
}
