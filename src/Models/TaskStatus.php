<?php

declare(strict_types=1);

namespace A2A\Models;

use A2A\Models\Message;

/**
 * Represents the status of a task at a specific point in time.
 *
 * @see https://a2a-protocol.org/dev/specification/#62-taskstatus-object
 */
class TaskStatus
{
    private TaskState $state;
    private ?Message $message;
    private ?string $timestamp;

    public function __construct(TaskState $state, ?Message $message = null, ?string $timestamp = null)
    {
        $this->state = $state;
        $this->message = $message;
        $this->timestamp = $timestamp ?? date('c');
    }

    public function getState(): TaskState
    {
        return $this->state;
    }

    public function getMessage(): ?Message
    {
        return $this->message;
    }

    public function getTimestamp(): ?string
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        $data = [
            'state' => $this->state->value,
            'timestamp' => $this->timestamp,
        ];

        if ($this->message !== null) {
            $data['message'] = $this->message->toArray();
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        $state = TaskState::from($data['state']);
        $message = isset($data['message']) ? Message::fromArray($data['message']) : null;
        $timestamp = $data['timestamp'] ?? null;

        return new self($state, $message, $timestamp);
    }
}
