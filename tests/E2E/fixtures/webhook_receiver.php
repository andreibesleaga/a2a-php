<?php

/**
 * Minimal webhook receiver used by the end-to-end test suite.
 *
 * Appends one JSON line per received request (headers + body) to the file
 * named by the A2A_TEST_WEBHOOK_LOG env var, then answers 204.
 *
 * Run with: A2A_TEST_WEBHOOK_LOG=/tmp/hooks.jsonl php -S localhost:<port> webhook_receiver.php
 */

declare(strict_types=1);

$logFile = getenv('A2A_TEST_WEBHOOK_LOG') ?: sys_get_temp_dir() . '/a2a_webhook_receiver.jsonl';

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $name = strtolower(str_replace('_', '-', substr($key, 5)));
        $headers[$name] = $value;
    }
}

$entry = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
];

file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);

http_response_code(204);
