<?php

declare(strict_types=1);

namespace A2A\Exceptions;

class ContentTypeNotSupportedException extends A2AException
{
    public function __construct(string $message = 'Content type not supported')
    {
        parent::__construct($message);
    }
}
