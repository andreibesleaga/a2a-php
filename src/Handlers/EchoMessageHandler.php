<?php

declare(strict_types=1);

namespace A2A\Handlers;

use A2A\Interfaces\MessageHandlerInterface;
use A2A\Models\v030\Message;
use A2A\Models\TextPart;

class EchoMessageHandler implements MessageHandlerInterface
{
    public function handle(Message $message, string $fromAgent): array
    {
        $textContent = '';
        foreach ($message->getParts() as $part) {
            if ($part instanceof TextPart) {
                $textContent = $part->getText();
                break; // Echo the first text part found
            }
        }

        return [
            'status' => 'processed',
            'echo' => $textContent,
            'from' => $fromAgent,
            'message_id' => $message->getMessageId(),
            'timestamp' => time()
        ];
    }

    public function canHandle(Message $message): bool
    {
        // This handler can process any message that contains at least one text part.
        foreach ($message->getParts() as $part) {
            if ($part instanceof TextPart) {
                return true;
            }
        }
        return false;
    }
}
