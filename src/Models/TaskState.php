<?php

declare(strict_types=1);

namespace A2A\Models;

enum TaskState: string
{
    case SUBMITTED = 'submitted';
    case WORKING = 'working';
    case INPUT_REQUIRED = 'input-required';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
    case AUTH_REQUIRED = 'auth-required';
    case UNKNOWN = 'unknown';

    public function isTerminal(): bool
    {
        return in_array(
            $this,
            [
            self::COMPLETED,
            self::CANCELED,
            self::FAILED,
            self::REJECTED,
            self::UNKNOWN
            ]
        );
    }
}
