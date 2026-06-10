<?php

declare(strict_types=1);

namespace A2A\Exceptions;

class InvalidAgentResponseException extends A2AException
{
    public function __construct(string $message = 'Invalid agent response')
    {
        parent::__construct($message);
    }
}
