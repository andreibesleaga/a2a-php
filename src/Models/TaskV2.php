<?php

declare(strict_types=1);

namespace A2A\Models;

use DateTime;
use DateTimeInterface;

/**
 * A2A Protocol compliant Task implementation
 */
class TaskV2
{
    private string $id;
    private string $contextId;
    private string $kind = 'task';
    private TaskStatus $status;
    private ?array $history = null;
    private ?array $artifacts = null;
    private ?array $metadata = null;

    public function __construct(
        string $id,
        string $contextId,
        TaskStatus $status
    ) {
        $this->id = $id;
        $this->contextId = $contextId;
        $this->status = $status;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getContextId(): string
    {
        return $this->contextId;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): void
    {
        $this->status = $status;
    }

    public function getHistory(): ?array
    {
        return $this->history;
    }

    public function setHistory(array $history): void
    {
        $this->history = $history;
    }

    public function addToHistory(MessageV2 $message): void
    {
        if ($this->history === null) {
            $this->history = [];
        }
        $this->history[] = $message;
    }

    public function getArtifacts(): ?array
    {
        return $this->artifacts;
    }

    public function setArtifacts(array $artifacts): void
    {
        $this->artifacts = $artifacts;
    }

    public function addArtifact(Artifact $artifact): void
    {
        if ($this->artifacts === null) {
            $this->artifacts = [];
        }
        $this->artifacts[] = $artifact;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status->getState()->value, ['completed', 'failed', 'canceled', 'rejected']);
    }

    public function toArray(): array
    {
        $result = [
            'kind' => $this->kind,
            'id' => $this->id,
            'contextId' => $this->contextId,
            'status' => $this->status->toArray()
        ];

        if ($this->history !== null) {
            $result['history'] = array_map(
                fn(MessageV2 $message) => $message->toArray(),
                $this->history
            );
        }

        if ($this->artifacts !== null) {
            $result['artifacts'] = array_map(
                fn(Artifact $artifact) => $artifact->toArray(),
                $this->artifacts
            );
        }

        if ($this->metadata !== null) {
            $result['metadata'] = $this->metadata;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        $status = TaskStatus::fromArray($data['status']);

        $task = new self(
            $data['id'],
            $data['contextId'],
            $status
        );

        if (isset($data['history'])) {
            $history = [];
            foreach ($data['history'] as $messageData) {
                $history[] = MessageV2::fromArray($messageData);
            }
            $task->setHistory($history);
        }

        if (isset($data['artifacts'])) {
            $artifacts = [];
            foreach ($data['artifacts'] as $artifactData) {
                $artifacts[] = Artifact::fromArray($artifactData);
            }
            $task->setArtifacts($artifacts);
        }

        if (isset($data['metadata'])) {
            $task->setMetadata($data['metadata']);
        }

        return $task;
    }
}
