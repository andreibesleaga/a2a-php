<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\InvalidRequestException;
use A2A\Models\TaskState;
use A2A\Models\TaskStatus;
use A2A\Models\v030\Message;
use A2A\Notifications\PushNotifier;
use A2A\TaskManager;
use A2A\Utils\JsonRpc;
use Psr\Log\LoggerInterface;

/**
 * Handles the legacy "tasks/send" method: submits a message to a task with
 * an explicit task ID, runs the registered message handlers, and completes
 * the task.
 */
class TaskSendHandler implements RequestHandlerInterface
{
    private TaskManager $taskManager;
    /** @var callable(Message, string): array */
    private $processMessage;
    private PushNotifier $pushNotifier;
    private LoggerInterface $logger;

    /**
     * @param callable(Message, string): array $processMessage Runs registered
     *        message handlers for a message and returns the handler result.
     */
    public function __construct(
        TaskManager $taskManager,
        callable $processMessage,
        PushNotifier $pushNotifier,
        LoggerInterface $logger
    ) {
        $this->taskManager = $taskManager;
        $this->processMessage = $processMessage;
        $this->pushNotifier = $pushNotifier;
        $this->logger = $logger;
    }

    public function handle(array $params, mixed $requestId): array
    {
        $jsonRpc = new JsonRpc();

        $taskId = $params['id'] ?? null;
        $message = $params['message'] ?? null;
        $metadata = $params['metadata'] ?? [];

        if (!$taskId || !$message) {
            throw new InvalidRequestException('Task ID and message are required');
        }

        try {
            $messageObj = Message::fromArray($message);

            $task = $this->taskManager->getTask($taskId);
            if (!$task) {
                $task = $this->taskManager->createTask(
                    'Task created via tasks/send',
                    array_merge($metadata, ['taskId' => $taskId]),
                    $taskId
                );
            }

            $task->addToHistory($messageObj);
            $task->setStatus(new TaskStatus(TaskState::WORKING));

            $result = ($this->processMessage)($messageObj, 'tasks/send');

            if (isset($result['artifacts'])) {
                foreach ($result['artifacts'] as $artifactData) {
                    $task->addArtifact($artifactData);
                }
            }

            $task->setStatus(new TaskStatus(TaskState::COMPLETED));
            $this->taskManager->updateTask($task);
            $this->pushNotifier->notify($task);

            return $jsonRpc->createResponse($requestId, $task->toArray());
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to send task', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
                ]
            );

            if (isset($task)) {
                $task->setStatus(new TaskStatus(TaskState::FAILED));
            }

            throw $e;
        }
    }
}
