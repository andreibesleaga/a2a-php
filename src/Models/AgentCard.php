<?php

declare(strict_types=1);

namespace A2A\Models;

use A2A\Models\Security\APIKeySecurityScheme;
use A2A\Models\Security\HTTPAuthSecurityScheme;
use A2A\Models\Security\MutualTLSSecurityScheme;
use A2A\Models\Security\OAuth2SecurityScheme;
use A2A\Models\Security\OpenIdConnectSecurityScheme;
use A2A\Models\Security\SecurityScheme;

/**
 * A2A Protocol compliant AgentCard implementation.
 *
 * An AgentCard conveys key information:
 * - Overall details (version, name, description, uses)
 * - Skills: A set of capabilities the agent can perform
 * - Default modalities/content types supported by the agent
 * - Authentication requirements
 *
 * @see https://a2a-protocol.org/dev/specification/#55-agentcard-object-structure
 */
class AgentCard
{
    private string $name;
    private string $description;
    private string $url;
    private string $version;
    private string $protocolVersion;
    private AgentCapabilities $capabilities;
    private array $defaultInputModes;
    private array $defaultOutputModes;
    private array $skills;
    private ?AgentProvider $provider = null;
    /** @var array<string, SecurityScheme>|null */
    private ?array $securitySchemes = null;
    private ?array $security = null;
    /** @var AgentInterface[]|null */
    private ?array $additionalInterfaces = null;
    private ?string $preferredTransport = null;
    private ?string $documentationUrl = null;
    private ?string $iconUrl = null;
    private bool $supportsAuthenticatedExtendedCard = false;
    /** @var AgentCardSignature[]|null */
    private ?array $signatures = null;

    public function __construct(
        string $name,
        string $description,
        string $url,
        string $version,
        AgentCapabilities $capabilities,
        array $defaultInputModes,
        array $defaultOutputModes,
        array $skills,
        string $protocolVersion = '0.3.0',
        ?string $preferredTransport = 'JSONRPC'
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->url = $url;
        $this->version = $version;
        $this->protocolVersion = $protocolVersion;
        $this->capabilities = $capabilities;
        $this->defaultInputModes = $defaultInputModes;
        $this->defaultOutputModes = $defaultOutputModes;
        $this->skills = $skills;
        $this->preferredTransport = $preferredTransport;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function getCapabilities(): AgentCapabilities
    {
        return $this->capabilities;
    }

    public function getDefaultInputModes(): array
    {
        return $this->defaultInputModes;
    }

    public function getDefaultOutputModes(): array
    {
        return $this->defaultOutputModes;
    }

    public function getSkills(): array
    {
        return $this->skills;
    }

    public function setProvider(AgentProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function getProvider(): ?AgentProvider
    {
        return $this->provider;
    }

    public function setSecuritySchemes(array $securitySchemes): void
    {
        $this->securitySchemes = $securitySchemes;
    }

    public function setSecurity(array $security): void
    {
        $this->security = $security;
    }

    public function setAdditionalInterfaces(array $additionalInterfaces): void
    {
        $this->additionalInterfaces = $additionalInterfaces;
    }

    public function setPreferredTransport(string $preferredTransport): void
    {
        $this->preferredTransport = $preferredTransport;
    }

    public function setDocumentationUrl(string $documentationUrl): void
    {
        $this->documentationUrl = $documentationUrl;
    }

    public function setIconUrl(string $iconUrl): void
    {
        $this->iconUrl = $iconUrl;
    }

    public function setSupportsAuthenticatedExtendedCard(bool $supports): void
    {
        $this->supportsAuthenticatedExtendedCard = $supports;
    }

    public function setSignatures(?array $signatures): void
    {
        $this->signatures = $signatures;
    }

    public function getSignatures(): ?array
    {
        return $this->signatures;
    }

    public function getSupportsAuthenticatedExtendedCard(): bool
    {
        return $this->supportsAuthenticatedExtendedCard;
    }

    public function addSkill(AgentSkill $skill): void
    {
        $this->skills[] = $skill;
    }

    public function addAdditionalInterface(AgentInterface $interface): void
    {
        if ($this->additionalInterfaces === null) {
            $this->additionalInterfaces = [];
        }
        $this->additionalInterfaces[] = $interface;
    }

    public function getAdditionalInterfaces(): ?array
    {
        return $this->additionalInterfaces;
    }

    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'description' => $this->description,
            'url' => $this->url,
            'version' => $this->version,
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities->toArray(),
            'defaultInputModes' => $this->defaultInputModes,
            'defaultOutputModes' => $this->defaultOutputModes,
            'skills' => array_map(fn (AgentSkill $skill) => $skill->toArray(), $this->skills),
            'supportsAuthenticatedExtendedCard' => $this->supportsAuthenticatedExtendedCard
        ];

        if ($this->provider !== null) {
            $result['provider'] = $this->provider->toArray();
        }

        if ($this->securitySchemes !== null) {
            $result['securitySchemes'] = array_map(fn (SecurityScheme $scheme) => $scheme->toArray(), $this->securitySchemes);
        }

        if ($this->security !== null) {
            $result['security'] = $this->security;
        }

        if ($this->additionalInterfaces !== null) {
            $result['additionalInterfaces'] = array_map(fn (AgentInterface $interface) => $interface->toArray(), $this->additionalInterfaces);
        }

        if ($this->preferredTransport !== null) {
            $result['preferredTransport'] = $this->preferredTransport;
        }

        if ($this->documentationUrl !== null) {
            $result['documentationUrl'] = $this->documentationUrl;
        }

        if ($this->iconUrl !== null) {
            $result['iconUrl'] = $this->iconUrl;
        }

        if ($this->signatures !== null) {
            $result['signatures'] = array_map(fn (AgentCardSignature $signature) => $signature->toArray(), $this->signatures);
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        $capabilities = AgentCapabilities::fromArray($data['capabilities'] ?? []);

        $skills = [];
        if (isset($data['skills'])) {
            foreach ($data['skills'] as $skillData) {
                $skills[] = AgentSkill::fromArray($skillData);
            }
        }

        $agentCard = new self(
            $data['name'],
            $data['description'],
            $data['url'],
            $data['version'],
            $capabilities,
            $data['defaultInputModes'] ?? [],
            $data['defaultOutputModes'] ?? [],
            $skills,
            $data['protocolVersion'] ?? '0.3.0',
            $data['preferredTransport'] ?? 'JSONRPC'
        );

        if (isset($data['provider'])) {
            $agentCard->setProvider(AgentProvider::fromArray($data['provider']));
        }

        if (isset($data['securitySchemes'])) {
            $schemes = [];
            foreach ($data['securitySchemes'] as $name => $schemeData) {
                switch ($schemeData['type']) {
                    case 'apiKey':
                        $schemes[$name] = APIKeySecurityScheme::fromArray($schemeData);
                        break;
                    case 'http':
                        $schemes[$name] = HTTPAuthSecurityScheme::fromArray($schemeData);
                        break;
                    case 'oauth2':
                        $schemes[$name] = OAuth2SecurityScheme::fromArray($schemeData);
                        break;
                    case 'openIdConnect':
                        $schemes[$name] = OpenIdConnectSecurityScheme::fromArray($schemeData);
                        break;
                    case 'mutualTLS':
                        $schemes[$name] = MutualTLSSecurityScheme::fromArray($schemeData);
                        break;
                }
            }
            $agentCard->setSecuritySchemes($schemes);
        }

        if (isset($data['security'])) {
            $agentCard->setSecurity($data['security']);
        }

        if (isset($data['additionalInterfaces'])) {
            $interfaces = [];
            foreach ($data['additionalInterfaces'] as $interfaceData) {
                $interfaces[] = AgentInterface::fromArray($interfaceData);
            }
            $agentCard->setAdditionalInterfaces($interfaces);
        }

        if (isset($data['documentationUrl'])) {
            $agentCard->setDocumentationUrl($data['documentationUrl']);
        }

        if (isset($data['iconUrl'])) {
            $agentCard->setIconUrl($data['iconUrl']);
        }

        if (isset($data['supportsAuthenticatedExtendedCard'])) {
            $agentCard->setSupportsAuthenticatedExtendedCard($data['supportsAuthenticatedExtendedCard']);
        }

        if (isset($data['signatures'])) {
            $signatures = [];
            foreach ($data['signatures'] as $signatureData) {
                $signatures[] = AgentCardSignature::fromArray($signatureData);
            }
            $agentCard->setSignatures($signatures);
        }

        return $agentCard;
    }
}
