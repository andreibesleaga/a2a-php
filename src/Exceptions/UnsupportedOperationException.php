<?php

declare(strict_types=1);

namespace A2A\Exceptions;

class UnsupportedOperationException extends A2AException
{
    public function __construct(string $message = 'Operation not supported')
    {
        parent::__construct($message);
    }
}
