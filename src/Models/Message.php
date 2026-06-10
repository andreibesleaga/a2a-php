<?php

declare(strict_types=1);

namespace A2A\Models;

use A2A\Exceptions\InvalidRequestException;
use Ramsey\Uuid\Uuid;

/**
 * Represents a single communication turn or a piece of contextual
 * information between a client and an agent.
 *
 * @see https://a2a-protocol.org/dev/specification/#64-message-object
 */
class Message
{
    private string $messageId;
    private string $role;
    private string $kind = 'message';
    /** @var PartInterface[] */
    private array $parts;
    private ?string $contextId = null;
    private ?string $taskId = null;
    private ?array $referenceTaskIds = null;
    private ?array $extensions = null;
    private ?array $metadata = null;

    public function __construct(
        string $messageId,
        string $role,
        array $parts
    ) {
        $this->messageId = $messageId;
        $this->role = $role;
        $this->parts = $parts;
    }

    public static function createUserMessage(string $text, ?string $messageId = null): self
    {
        $messageId = $messageId ?? Uuid::uuid4()->toString();
        $textPart = new TextPart($text);

        return new self($messageId, 'user', [$textPart]);
    }

    public static function createAgentMessage(string $text, ?string $messageId = null): self
    {
        $messageId = $messageId ?? Uuid::uuid4()->toString();
        $textPart = new TextPart($text);

        return new self($messageId, 'agent', [$textPart]);
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getParts(): array
    {
        return $this->parts;
    }

    public function addPart(PartInterface $part): void
    {
        $this->parts[] = $part;
    }

    public function getContextId(): ?string
    {
        return $this->contextId;
    }

    public function setContextId(string $contextId): void
    {
        $this->contextId = $contextId;
    }

    public function getTaskId(): ?string
    {
        return $this->taskId;
    }

    public function setTaskId(string $taskId): void
    {
        $this->taskId = $taskId;
    }

    public function getReferenceTaskIds(): ?array
    {
        return $this->referenceTaskIds;
    }

    public function setReferenceTaskIds(array $referenceTaskIds): void
    {
        $this->referenceTaskIds = $referenceTaskIds;
    }

    public function getExtensions(): ?array
    {
        return $this->extensions;
    }

    public function setExtensions(array $extensions): void
    {
        $this->extensions = $extensions;
    }

    public function getMetadata(): ?array
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
            'kind' => $this->kind,
            'messageId' => $this->messageId,
            'role' => $this->role,
            'parts' => array_map(fn (PartInterface $part) => $part->toArray(), $this->parts)
        ];

        if ($this->contextId !== null) {
            $result['contextId'] = $this->contextId;
        }

        if ($this->taskId !== null) {
            $result['taskId'] = $this->taskId;
        }

        if ($this->referenceTaskIds !== null) {
            $result['referenceTaskIds'] = $this->referenceTaskIds;
        }

        if ($this->extensions !== null) {
            $result['extensions'] = $this->extensions;
        }

        if ($this->metadata !== null) {
            $result['metadata'] = $this->metadata;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['messageId']) || !is_string($data['messageId']) || $data['messageId'] === '') {
            throw new InvalidRequestException('Message requires a non-empty string "messageId" field');
        }

        if (!isset($data['role']) || !is_string($data['role']) || $data['role'] === '') {
            throw new InvalidRequestException('Message requires a non-empty string "role" field');
        }

        $parts = [];
        if (isset($data['parts'])) {
            foreach ($data['parts'] as $partData) {
                $parts[] = Part::fromArray($partData);
            }
        }

        $message = new self(
            $data['messageId'],
            $data['role'],
            $parts
        );

        if (isset($data['contextId'])) {
            $message->setContextId($data['contextId']);
        }

        if (isset($data['taskId'])) {
            $message->setTaskId($data['taskId']);
        }

        if (isset($data['referenceTaskIds'])) {
            $message->setReferenceTaskIds($data['referenceTaskIds']);
        }

        if (isset($data['extensions'])) {
            $message->setExtensions($data['extensions']);
        }

        if (isset($data['metadata'])) {
            $message->setMetadata($data['metadata']);
        }

        return $message;
    }
}
