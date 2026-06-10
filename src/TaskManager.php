<?php

declare(strict_types=1);

namespace A2A;

use A2A\Exceptions\A2AErrorCodes;
use A2A\Models\v030\Task;
use A2A\Models\TaskState;
use A2A\Models\TaskStatus;
use A2A\Storage\Storage;
use Ramsey\Uuid\Uuid;

class TaskManager
{
    private Storage $storage;

    public function __construct(?Storage $storage = null)
    {
        $this->storage = $storage ?? new Storage('array');
    }

    public function createTask(string $description, array $context = [], ?string $taskId = null): Task
    {
        $taskId = $taskId ?? Uuid::uuid4()->toString();
        $contextId = $context['contextId'] ?? Uuid::uuid4()->toString();
        $status = new TaskStatus(TaskState::SUBMITTED);
        $metadata = $context;
        $metadata['description'] = $description;

        $task = new Task($taskId, $contextId, $status, [], [], $metadata);
        $this->storage->saveTask($task);
        return $task;
    }

    public function getTask(string $taskId): ?Task
    {
        return $this->storage->getTask($taskId);
    }

    public function updateTask(Task $task): void
    {
        $this->storage->saveTask($task);
    }

    public function cancelTask(string $taskId): array
    {
        $task = $this->getTask($taskId);
        if (!$task) {
            return [
                'error' => [
                    'code' => A2AErrorCodes::TASK_NOT_FOUND,
                    'message' => 'Task not found'
                ]
            ];
        }

        if ($task->getStatus()->getState() === TaskState::CANCELED) {
            return [
                'error' => [
                    'code' => A2AErrorCodes::TASK_NOT_CANCELABLE,
                    'message' => 'Task has already been canceled'
                ]
            ];
        }

        if ($task->getStatus()->getState()->isTerminal()) {
            return [
                'error' => [
                    'code' => A2AErrorCodes::TASK_NOT_CANCELABLE,
                    'message' => 'Task is already in a terminal state'
                ]
            ];
        }

        $task->setStatus(new TaskStatus(TaskState::CANCELED));
        $this->updateTask($task);

        return [
            'result' => $task->toArray()
        ];
    }
}
