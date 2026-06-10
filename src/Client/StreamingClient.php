<?php

declare(strict_types=1);

namespace A2A\Client;

use A2A\Models\AgentCard;
use A2A\Models\Message;
use A2A\Utils\JsonRpc;
use A2A\Exceptions\A2AException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class StreamingClient
{
    private AgentCard $agentCard;
    private LoggerInterface $logger;

    public function __construct(AgentCard $agentCard, ?LoggerInterface $logger = null)
    {
        $this->agentCard = $agentCard;
        $this->logger = $logger ?? new NullLogger();
    }

    public function sendMessageStream(string $agentUrl, Message $message, callable $eventHandler): void
    {
        $jsonRpc = new JsonRpc();
        $request = $jsonRpc->createRequest(
            'message/stream',
            [
            'message' => $message->toArray()
            ],
            1
        );

        $this->streamRequest($agentUrl, $request, $eventHandler);
    }

    public function resubscribeTask(string $agentUrl, string $taskId, callable $eventHandler): void
    {
        $jsonRpc = new JsonRpc();
        $request = $jsonRpc->createRequest(
            'tasks/resubscribe',
            [
            'id' => $taskId
            ],
            1
        );

        $this->streamRequest($agentUrl, $request, $eventHandler);
    }

    private function streamRequest(string $url, array $request, callable $eventHandler): void
    {
        $context = stream_context_create(
            [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: text/event-stream\r\n",
                'content' => json_encode($request)
            ]
            ]
        );

        $stream = fopen($url, 'r', false, $context);
        if (!$stream) {
            throw new A2AException('Failed to open stream');
        }

        try {
            while (!feof($stream)) {
                $line = fgets($stream);
                if ($line && str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);
                    $event = json_decode(trim($data), true);
                    if ($event) {
                        $eventHandler($event);
                    }
                }
            }
        } finally {
            fclose($stream);
        }
    }
}
