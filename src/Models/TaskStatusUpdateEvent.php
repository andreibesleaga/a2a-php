<?php

declare(strict_types=1);

namespace A2A\Models;

/**
 * An event sent by the agent to notify the client of a change in a task's status.
 * This is typically used in streaming or subscription models.
 *
 * @see https://a2a-protocol.org/dev/specification/#722-taskstatusupdateevent-object
 */
class TaskStatusUpdateEvent
{
    private string $taskId;
    private string $contextId;
    private string $kind = 'status-update';
    private TaskStatus $status;
    private bool $final;
    private ?array $metadata;

    public function __construct(
        string $taskId,
        string $contextId,
        TaskStatus $status,
        bool $final = false,
        ?array $metadata = null
    ) {
        $this->taskId = $taskId;
        $this->contextId = $contextId;
        $this->status = $status;
        $this->final = $final;
        $this->metadata = $metadata;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getContextId(): string
    {
        return $this->contextId;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function isFinal(): bool
    {
        return $this->final;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        $data = [
            'kind' => $this->kind,
            'taskId' => $this->taskId,
            'contextId' => $this->contextId,
            'status' => $this->status->toArray(),
            'final' => $this->final,
        ];

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }
}
