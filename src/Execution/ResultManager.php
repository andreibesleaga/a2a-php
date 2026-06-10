<?php

declare(strict_types=1);

namespace A2A\Execution;

use A2A\Interfaces\ExecutionEventBus;
use A2A\Models\TaskStatusUpdateEvent;
use A2A\Models\TaskArtifactUpdateEvent;

class ResultManager
{
    private array $results = [];

    public function processEvents(ExecutionEventBus $eventBus, string $taskId): void
    {
        $eventBus->subscribe(
            $taskId,
            function ($event) use ($taskId) {
                $this->handleEvent($taskId, $event);
            }
        );
    }

    private function handleEvent(string $taskId, object $event): void
    {
        if (!isset($this->results[$taskId])) {
            $this->results[$taskId] = [];
        }

        if ($event instanceof TaskStatusUpdateEvent) {
            $this->results[$taskId]['status'] = $event->getStatus();
            if ($event->isFinal()) {
                $this->results[$taskId]['completed'] = true;
            }
        } elseif ($event instanceof TaskArtifactUpdateEvent) {
            if (!isset($this->results[$taskId]['artifacts'])) {
                $this->results[$taskId]['artifacts'] = [];
            }
            $this->results[$taskId]['artifacts'][] = $event->getArtifact();
        }
    }

    public function getResult(string $taskId): ?array
    {
        return $this->results[$taskId] ?? null;
    }

    public function cleanup(string $taskId): void
    {
        unset($this->results[$taskId]);
    }
}
