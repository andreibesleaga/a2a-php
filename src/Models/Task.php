<?php

declare(strict_types=1);

namespace A2A\Models;

use Ramsey\Uuid\Uuid;

/**
 * Represents the stateful unit of work being processed by the A2A Server.
 *
 * @see https://a2a-protocol.org/dev/specification/#61-task-object
 */
class Task
{
    private string $id;
    private string $contextId;
    private TaskStatus $status;
    private array $history;
    private array $artifacts;
    private array $metadata;

    public function __construct(
        string $id,
        string $contextId,
        TaskStatus $status,
        array $history = [],
        array $artifacts = [],
        array $metadata = []
    ) {
        $this->id = $id;
        $this->contextId = $contextId;
        $this->status = $status;
        $this->history = $history;
        $this->artifacts = $artifacts;
        $this->metadata = $metadata;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getContextId(): string
    {
        return $this->contextId;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): void
    {
        $this->status = $status;
    }

    public function getHistory(int $limit = null): array
    {
        if ($limit === null) {
            return $this->history;
        }
        return array_slice($this->history, -$limit);
    }

    public function addToHistory(Message $message): void
    {
        $this->history[] = $message;
    }

    public function getArtifacts(): array
    {
        return $this->artifacts;
    }

    public function addArtifact(Artifact $artifact): void
    {
        $this->artifacts[] = $artifact;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function toArray(): array
    {
        $result = [
            'kind' => 'task',
            'id' => $this->id,
            'contextId' => $this->contextId,
            'status' => $this->status->toArray(),
        ];

        if (!empty($this->artifacts)) {
            $result['artifacts'] = array_map(fn(Artifact $artifact) => $artifact->toArray(), $this->artifacts);
        }

        if (!empty($this->history)) {
            $result['history'] = array_map(fn(Message $message) => $message->toArray(), $this->history);
        }

        if (!empty($this->metadata)) {
            $result['metadata'] = $this->metadata;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        $status = TaskStatus::fromArray($data['status']);

        $history = [];
        if (isset($data['history'])) {
            foreach ($data['history'] as $messageData) {
                $history[] = Message::fromArray($messageData);
            }
        }

        $artifacts = [];
        if (isset($data['artifacts'])) {
            foreach ($data['artifacts'] as $artifactData) {
                $artifacts[] = Artifact::fromArray($artifactData);
            }
        }

        return new self(
            $data['id'],
            $data['contextId'] ?? Uuid::uuid4()->toString(),
            $status,
            $history,
            $artifacts,
            $data['metadata'] ?? []
        );
    }
}
