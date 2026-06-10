<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * Represents a unit of capability that an agent can perform
 */
class AgentSkill
{
    private string $id;
    private string $name;
    private string $description;
    private array $tags;
    private ?array $examples = null;
    private ?array $inputModes = null;
    private ?array $outputModes = null;

    public function __construct(
        string $id,
        string $name,
        string $description,
        array $tags
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->tags = $tags;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setExamples(array $examples): void
    {
        $this->examples = $examples;
    }

    public function getExamples(): ?array
    {
        return $this->examples;
    }

    public function setInputModes(array $inputModes): void
    {
        $this->inputModes = $inputModes;
    }

    public function getInputModes(): ?array
    {
        return $this->inputModes;
    }

    public function setOutputModes(array $outputModes): void
    {
        $this->outputModes = $outputModes;
    }

    public function getOutputModes(): ?array
    {
        return $this->outputModes;
    }

    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'tags' => $this->tags
        ];

        if ($this->examples !== null) {
            $result['examples'] = $this->examples;
        }

        if ($this->inputModes !== null) {
            $result['inputModes'] = $this->inputModes;
        }

        if ($this->outputModes !== null) {
            $result['outputModes'] = $this->outputModes;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        $skill = new self(
            $data['id'],
            $data['name'],
            $data['description'],
            $data['tags'] ?? []
        );

        if (isset($data['examples'])) {
            $skill->setExamples($data['examples']);
        }

        if (isset($data['inputModes'])) {
            $skill->setInputModes($data['inputModes']);
        }

        if (isset($data['outputModes'])) {
            $skill->setOutputModes($data['outputModes']);
        }

        return $skill;
    }
}
