<?php

declare(strict_types=1);

namespace A2A\Tests\Models;

use A2A\Models\Artifact;
use PHPUnit\Framework\TestCase;
use A2A\Models\Task;
use A2A\Models\TaskStatus;
use A2A\Models\TaskState;
use A2A\Models\Message;
use A2A\Models\Part;
use Ramsey\Uuid\Uuid;

class TaskTest extends TestCase
{
    private function createTask(array $metadata = []): Task
    {
        $status = new TaskStatus(TaskState::SUBMITTED);
        $metadata['description'] = 'Test task';

        return new Task(
            'task-123',
            Uuid::uuid4()->toString(),
            $status,
            [],
            [],
            $metadata
        );
    }

    public function testCreateTask(): void
    {
        $task = $this->createTask(['priority' => 'high']);

        $this->assertEquals('task-123', $task->getId());
        $this->assertEquals('Test task', $task->getMetadata()['description']);
        $this->assertEquals('high', $task->getMetadata()['priority']);
        $this->assertEquals(TaskState::SUBMITTED, $task->getStatus()->getState());
    }

    public function testTaskStatusTransitions(): void
    {
        $task = $this->createTask();

        $newStatus = new TaskStatus(TaskState::WORKING);
        $task->setStatus($newStatus);
        $this->assertEquals(TaskState::WORKING, $task->getStatus()->getState());

        $completedStatus = new TaskStatus(TaskState::COMPLETED);
        $task->setStatus($completedStatus);
        $this->assertEquals(TaskState::COMPLETED, $task->getStatus()->getState());
    }

    public function testTaskHistory(): void
    {
        $task = $this->createTask();
        $message1 = Message::createUserMessage('First message');
        $message2 = Message::createUserMessage('Second message');

        $task->addToHistory($message1);
        $task->addToHistory($message2);

        $this->assertCount(2, $task->getHistory());
        $this->assertCount(1, $task->getHistory(1));
        $this->assertEquals($message2, $task->getHistory(1)[0]);
    }

    public function testTaskArtifacts(): void
    {
        $task = $this->createTask();
        $part = new Part('text', 'artifact content');
        $artifact = new Artifact('artifact-1', [$part]);
        $task->addArtifact($artifact);

        $this->assertCount(1, $task->getArtifacts());
        $this->assertEquals($artifact, $task->getArtifacts()[0]);
    }

    public function testToArray(): void
    {
        $task = $this->createTask(['key' => 'value']);
        $array = $task->toArray();

        $this->assertEquals('task', $array['kind']);
        $this->assertEquals('task-123', $array['id']);
        $this->assertEquals('submitted', $array['status']['state']);
        $this->assertEquals('Test task', $array['metadata']['description']);
        $this->assertEquals('value', $array['metadata']['key']);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'task-456',
            'contextId' => 'ctx-789',
            'status' => ['state' => 'working', 'timestamp' => date('c')],
            'metadata' => ['description' => 'Another task', 'type' => 'test'],
            'kind' => 'task'
        ];

        $task = Task::fromArray($data);

        $this->assertEquals('task-456', $task->getId());
        $this->assertEquals(TaskState::WORKING, $task->getStatus()->getState());
        $this->assertEquals('Another task', $task->getMetadata()['description']);
        $this->assertEquals('test', $task->getMetadata()['type']);
    }
}
