<?php

declare(strict_types=1);

namespace A2A\Tests;

use PHPUnit\Framework\TestCase;
use A2A\TaskManager;
use A2A\Models\TaskState;
use A2A\Models\TaskStatus;
use A2A\Storage\Storage;

class TaskManagerTest extends TestCase
{
    private TaskManager $taskManager;

    protected function setUp(): void
    {
        $this->taskManager = new TaskManager(new Storage('array'));
    }

    public function testCreateTask(): void
    {
        $task = $this->taskManager->createTask('Test task', ['priority' => 'high']);

        $this->assertNotEmpty($task->getId());
        $this->assertEquals('Test task', $task->getMetadata()['description']);
        $this->assertEquals('high', $task->getMetadata()['priority']);
        $this->assertEquals(TaskState::SUBMITTED, $task->getStatus()->getState());
    }

    public function testGetTask(): void
    {
        $task = $this->taskManager->createTask('Test task');
        $retrieved = $this->taskManager->getTask($task->getId());

        $this->assertEquals($task->getId(), $retrieved->getId());
    }

    public function testGetNonExistentTask(): void
    {
        $result = $this->taskManager->getTask('non-existent');
        $this->assertNull($result);
    }

    public function testCancelTask(): void
    {
        $task = $this->taskManager->createTask('Test task');
        $result = $this->taskManager->cancelTask($task->getId());

        $this->assertArrayHasKey('result', $result);
        $updatedTask = $this->taskManager->getTask($task->getId());
        $this->assertEquals(TaskState::CANCELED, $updatedTask->getStatus()->getState());
    }

    public function testCancelNonExistentTask(): void
    {
        $result = $this->taskManager->cancelTask('non-existent');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Task not found', $result['error']['message']);
    }

    public function testCancelCompletedTask(): void
    {
        $task = $this->taskManager->createTask('Test task');
        $task->setStatus(new TaskStatus(TaskState::COMPLETED));
        $this->taskManager->updateTask($task);

        $result = $this->taskManager->cancelTask($task->getId());

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Task is already in a terminal state', $result['error']['message']);
    }

    public function testUpdateTask(): void
    {
        $task = $this->taskManager->createTask('Test task');
        $task->setStatus(new TaskStatus(TaskState::WORKING));
        $this->taskManager->updateTask($task);

        $updatedTask = $this->taskManager->getTask($task->getId());
        $this->assertEquals(TaskState::WORKING, $updatedTask->getStatus()->getState());
    }
}
