<?php

declare(strict_types=1);

namespace A2A\Events;

use A2A\Interfaces\ExecutionEventBus;

class EventBusManager
{
    private array $eventBuses = [];

    public function getEventBus(string $taskId): ExecutionEventBus
    {
        if (!isset($this->eventBuses[$taskId])) {
            $this->eventBuses[$taskId] = new ExecutionEventBusImpl();
        }
        return $this->eventBuses[$taskId];
    }

    public function removeEventBus(string $taskId): void
    {
        unset($this->eventBuses[$taskId]);
    }

    public function cleanup(): void
    {
        $this->eventBuses = [];
    }
}
