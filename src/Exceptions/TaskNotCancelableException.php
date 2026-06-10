<?php

declare(strict_types=1);

namespace A2A\Exceptions;

class TaskNotCancelableException extends A2AException
{
    public function __construct(string $message = 'Task cannot be canceled')
    {
        parent::__construct($message);
    }
}
