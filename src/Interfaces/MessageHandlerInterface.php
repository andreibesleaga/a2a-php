<?php

declare(strict_types=1);

namespace A2A\Interfaces;

use A2A\Models\v030\Message;

interface MessageHandlerInterface
{
    /**
     * Process an incoming message and return response data
     */
    public function handle(Message $message, string $fromAgent): array;

    /**
     * Check if this handler can process the given message
     */
    public function canHandle(Message $message): bool;
}
