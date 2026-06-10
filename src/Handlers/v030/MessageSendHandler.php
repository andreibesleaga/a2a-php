<?php

declare(strict_types=1);

namespace A2A\Handlers\v030;

use A2A\Exceptions\A2AErrorCodes;
use A2A\Models\Artifact;
use A2A\Models\TaskState;
use A2A\Models\TaskStatus;
use A2A\Models\v030\Message;
use A2A\Models\v030\Task;
use A2A\Notifications\PushNotifier;
use A2A\TaskManager;
use A2A\Utils\JsonRpc;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Handles the "message/send" method: validates the message payload,
 * attaches it to a (possibly new) task, runs the registered message
 * handlers, and persists the resulting task state.
 */
class MessageSendHandler implements RequestHandlerInterface
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

        if (!isset($params['message']) || !is_array($params['message'])) {
            return $jsonRpc->createError(
                $requestId,
                'Invalid or missing message payload',
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        $messagePayload = $params['message'];

        if (empty($messagePayload['parts']) || !is_array($messagePayload['parts'])) {
            return $jsonRpc->createError(
                $requestId,
                'Message parts must be a non-empty array',
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        try {
            $message = Message::fromArray($messagePayload);
        } catch (\Throwable $e) {
            $this->logger->warning('Message payload validation failed', [
                'error' => $e->getMessage(),
                'payload' => $messagePayload
            ]);

            return $jsonRpc->createError(
                $requestId,
                'Invalid message structure: ' . $e->getMessage(),
                A2AErrorCodes::INVALID_PARAMS
            );
        }

        $fromAgent = $params['from'] ?? 'unknown';

        $taskId = $message->getTaskId() ?? Uuid::uuid4()->toString();
        if ($message->getTaskId() === null) {
            $message->setTaskId($taskId);
        }

        $contextId = $message->getContextId() ?? Uuid::uuid4()->toString();
        if ($message->getContextId() === null) {
            $message->setContextId($contextId);
        }

        $task = $this->taskManager->getTask($taskId);

        if (!$task) {
            $task = $this->taskManager->createTask(
                $messagePayload['metadata']['description'] ?? 'Message processing task',
                [
                    'contextId' => $contextId,
                    'source' => 'message/send',
                    'fromAgent' => $fromAgent
                ],
                $taskId
            );
        }

        $task->addToHistory($message);
        $task->setStatus(new TaskStatus(TaskState::WORKING));
        $this->taskManager->updateTask($task);

        $handlerResult = ($this->processMessage)($message, $fromAgent);

        $metadata = $task->getMetadata();

        if (is_array($handlerResult)) {
            if (!empty($handlerResult['metadata']) && is_array($handlerResult['metadata'])) {
                $metadata = array_merge($metadata, $handlerResult['metadata']);
            }

            $knownKeys = ['status', 'artifacts', 'metadata'];
            $additionalMetadata = [];
            foreach ($handlerResult as $key => $value) {
                if (!in_array($key, $knownKeys, true)) {
                    $additionalMetadata[$key] = $value;
                }
            }

            if (!empty($additionalMetadata)) {
                $metadata = array_merge($metadata, $additionalMetadata);
            }
        }

        $task->setMetadata($metadata);

        if (is_array($handlerResult) && !empty($handlerResult['artifacts']) && is_array($handlerResult['artifacts'])) {
            foreach ($handlerResult['artifacts'] as $artifactData) {
                $this->attachArtifactToTask($task, $artifactData);
            }
        }

        $task->setStatus($this->resolveTaskStatus($handlerResult));
        $this->taskManager->updateTask($task);
        $this->pushNotifier->notify($task);

        $this->logger->info(
            'Message processed',
            [
            'from' => $fromAgent,
            'message_id' => $message->getMessageId(),
            'task_id' => $task->getId(),
            'state' => $task->getStatus()->getState()->value
            ]
        );

        return $jsonRpc->createResponse($requestId, $task->toArray());
    }

    private function attachArtifactToTask(Task $task, mixed $artifactData): void
    {
        try {
            if ($artifactData instanceof Artifact) {
                $task->addArtifact($artifactData);
                return;
            }

            if (is_array($artifactData)) {
                $task->addArtifact(Artifact::fromArray($artifactData));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to attach artifact to task', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function resolveTaskStatus(mixed $handlerResult): TaskStatus
    {
        if ($handlerResult instanceof TaskStatus) {
            return $handlerResult;
        }

        $statusPayload = null;

        if (is_array($handlerResult) && array_key_exists('status', $handlerResult)) {
            $statusPayload = $handlerResult['status'];
        }

        if ($statusPayload instanceof TaskStatus) {
            return $statusPayload;
        }

        $state = TaskState::COMPLETED;
        $statusMessage = null;

        if (is_array($statusPayload)) {
            if (isset($statusPayload['state']) && is_string($statusPayload['state'])) {
                $state = $this->mapStringToTaskState($statusPayload['state']);
            }

            if (isset($statusPayload['message']) && is_array($statusPayload['message'])) {
                $statusMessage = $this->createStatusMessageFromArray($statusPayload['message']);
            }
        } elseif (is_string($statusPayload)) {
            $state = $this->mapStringToTaskState($statusPayload);
        }

        return new TaskStatus($state, $statusMessage);
    }

    private function mapStringToTaskState(string $state): TaskState
    {
        $normalized = strtolower($state);

        return match ($normalized) {
            'submitted' => TaskState::SUBMITTED,
            'working', 'in-progress', 'processing' => TaskState::WORKING,
            'input-required', 'input_required', 'awaiting_input', 'awaiting-input' => TaskState::INPUT_REQUIRED,
            'canceled', 'cancelled' => TaskState::CANCELED,
            'failed', 'error' => TaskState::FAILED,
            'rejected' => TaskState::REJECTED,
            'auth-required', 'authentication_required', 'authentication-required' => TaskState::AUTH_REQUIRED,
            'received' => TaskState::SUBMITTED,
            'completed', 'done', 'success', 'processed' => TaskState::COMPLETED,
            default => TaskState::COMPLETED,
        };
    }

    private function createStatusMessageFromArray(array $data): ?Message
    {
        try {
            return Message::fromArray($data);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to hydrate status message from array', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
