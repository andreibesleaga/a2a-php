<?php

declare(strict_types=1);

namespace A2A\Tests\Models;

use PHPUnit\Framework\TestCase;
use A2A\Models\Task;
use A2A\Models\TaskState;
use A2A\Models\TaskStatus;

class TaskV2Test extends TestCase
{
    private function createTask(string $id, string $contextId, TaskState $state): Task
    {
        $status = new TaskStatus($state);
        return new Task($id, $contextId, $status);
    }

    public function testCreateTask(): void
    {
        $task = $this->createTask('task-123', 'ctx-123', TaskState::SUBMITTED);

        $this->assertEquals('task-123', $task->getId());
        $this->assertEquals('ctx-123', $task->getContextId());
        $this->assertEquals(TaskState::SUBMITTED, $task->getStatus()->getState());
    }

    public function testToArray(): void
    {
        $task = $this->createTask('task-123', 'ctx-123', TaskState::SUBMITTED);
        $array = $task->toArray();

        $this->assertEquals('task', $array['kind']);
        $this->assertEquals('task-123', $array['id']);
        $this->assertEquals('ctx-123', $array['contextId']);
        $this->assertEquals('submitted', $array['status']['state']);
        $this->assertArrayHasKey('timestamp', $array['status']);
    }

    public function testTaskStates(): void
    {
        $task = $this->createTask('task-123', 'ctx-123', TaskState::SUBMITTED);

        $task->setStatus(new TaskStatus(TaskState::WORKING));
        $this->assertEquals(TaskState::WORKING, $task->getStatus()->getState());

        // A Task's status is terminal if it's completed, failed, or canceled.
        // We'll simulate this by setting the status directly.
        $task->setStatus(new TaskStatus(TaskState::COMPLETED));
        $this->assertTrue($task->getStatus()->getState()->isTerminal());
    }
}
