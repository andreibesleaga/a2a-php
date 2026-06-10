<?php

declare(strict_types=1);

namespace A2A\Exceptions;

class PushNotificationNotSupportedException extends A2AException
{
    public function __construct(string $message = 'Push notifications not supported')
    {
        parent::__construct($message);
    }
}
