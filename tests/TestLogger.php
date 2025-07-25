<?php

declare(strict_types=1);

namespace A2A\Tests;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class TestLogger implements LoggerInterface
{
    private array $records = [];

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => time()
        ];
    }

    public function getRecords(): array
    {
        return $this->records;
    }

    public function hasRecord(string $level, string $message): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }
        return false;
    }

    public function hasRecordThatContains(string $level, string $needle): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && strpos($record['message'], $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
