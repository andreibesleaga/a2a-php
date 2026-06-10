<?php

declare(strict_types=1);

// Keep PHP warnings/notices out of the HTTP response body: anything printed
// before the JSON-RPC payload corrupts the protocol stream for strict clients.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use A2A\Models\v030\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\AgentSkill;

// In-memory task storage
$tasks = [];
$pushConfigs = [];

// Handle agent card endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['REQUEST_URI'] === '/.well-known/agent-card.json') {
    $capabilities = new AgentCapabilities(true, true, false, []);
    $skills = [
        new AgentSkill('text-processing', 'Text Processing', 'Process text messages', ['text'])
    ];
    
    $agentCard = new AgentCard(
        'compliant-a2a-server',
        'Fully Compliant A2A Server for TCK Testing',
        'http://localhost:8081',
        '1.0.0',
        $capabilities,
        ['text/plain'],
        ['text/plain'],
        $skills
    );
    
    header('Content-Type: application/json');
    echo json_encode($agentCard->toArray());
    exit;
}

// Handle JSON-RPC requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    
    // Check for JSON parse errors first (JSON-RPC 2.0 §4.2)
    $request = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32700, 'message' => 'Parse error'],
            'id' => null
        ]);
        exit;
    }
    
    // Validate JSON-RPC 2.0 structure
    if (!is_array($request)) {
        echo json_encode([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32600, 'message' => 'Invalid Request'],
            'id' => null
        ]);
        exit;
    }
    
    // Check for required jsonrpc field
    if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0') {
        echo json_encode([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32600, 'message' => 'Invalid Request'],
            'id' => $request['id'] ?? null
        ]);
        exit;
    }
    
    // Check for required method field
    if (!isset($request['method'])) {
        echo json_encode([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32600, 'message' => 'Invalid Request'],
            'id' => $request['id'] ?? null
        ]);
        exit;
    }

    // Validate ID field - must be string, number, or null (not object/array)
    if (isset($request['id']) && !is_string($request['id']) && !is_numeric($request['id']) && $request['id'] !== null) {
        echo json_encode([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32600, 'message' => 'Invalid Request'],
            'id' => null
        ]);
        exit;
    }
    
    switch ($request['method']) {
        case 'ping':
            echo json_encode([
                'jsonrpc' => '2.0',
                'result' => ['status' => 'pong'],
                'id' => $request['id']
            ]);
            break;
            
        case 'message/send':
            // Validate message structure
            if (!isset($request['params']['message'])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]);
                break;
            }

            $message = $request['params']['message'];
            
            // Validate required message fields
            if (!isset($message['messageId']) || !isset($message['role']) || !isset($message['parts'])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]);
                break;
            }

            // Validate parts array is not empty
            if (!is_array($message['parts']) || empty($message['parts'])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]);
                break;
            }

            // Create task and return task object
            $taskId = 'task-' . uniqid();
            $contextId = $message['contextId'] ?? 'ctx-' . uniqid();
            
            $task = [
                'kind' => 'task',
                'id' => $taskId,
                'contextId' => $contextId,
                'status' => [
                    'state' => 'completed',
                    'timestamp' => date('c')
                ]
            ];
            
            $tasks[$taskId] = $task;
            
            echo json_encode([
                'jsonrpc' => '2.0',
                'result' => $task,
                'id' => $request['id']
            ]);
            break;

        case 'message/stream':
            // Handle streaming requests with SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            
            // Validate message structure
            if (!isset($request['params']['message'])) {
                echo "event: error\n";
                echo "data: " . json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]) . "\n\n";
                exit;
            }

            $message = $request['params']['message'];
            $taskId = 'stream-task-' . uniqid();
            $contextId = $message['contextId'] ?? 'ctx-' . uniqid();
            
            // Send initial response
            echo "data: " . json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'kind' => 'task',
                    'id' => $taskId,
                    'contextId' => $contextId,
                    'status' => [
                        'state' => 'working',
                        'timestamp' => date('c')
                    ]
                ],
                'id' => $request['id']
            ]) . "\n\n";
            
            // Send completion event
            echo "data: " . json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'kind' => 'status-update',
                    'taskId' => $taskId,
                    'contextId' => $contextId,
                    'status' => [
                        'state' => 'completed',
                        'timestamp' => date('c')
                    ],
                    'final' => true
                ],
                'id' => $request['id']
            ]) . "\n\n";
            
            exit;
            
        case 'tasks/get':
            if (!isset($request['params']['id'])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]);
                break;
            }
            
            $taskId = $request['params']['id'];
            if (isset($tasks[$taskId])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'result' => $tasks[$taskId],
                    'id' => $request['id']
                ]);
            } else {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32001, 'message' => 'Task not found'],
                    'id' => $request['id']
                ]);
            }
            break;
            
        case 'tasks/cancel':
            if (!isset($request['params']['id'])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]);
                break;
            }
            
            $taskId = $request['params']['id'];
            if (isset($tasks[$taskId])) {
                $tasks[$taskId]['status'] = [
                    'state' => 'canceled',
                    'timestamp' => date('c')
                ];
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'result' => [
                        'status' => 'canceled',
                        'taskId' => $taskId
                    ],
                    'id' => $request['id']
                ]);
            } else {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32001, 'message' => 'Task not found'],
                    'id' => $request['id']
                ]);
            }
            break;

        case 'tasks/resubscribe':
            // Handle task resubscription with SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            
            if (!isset($request['params']['id'])) {
                echo "event: error\n";
                echo "data: " . json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]) . "\n\n";
                exit;
            }
            
            $taskId = $request['params']['id'];
            if (!isset($tasks[$taskId])) {
                echo "event: error\n";
                echo "data: " . json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32001, 'message' => 'Task not found'],
                    'id' => $request['id']
                ]) . "\n\n";
                exit;
            }
            
            // Send subscription confirmation
            echo "data: " . json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'status' => 'subscribed',
                    'taskId' => $taskId
                ],
                'id' => $request['id']
            ]) . "\n\n";
            
            // Send current task state
            echo "data: " . json_encode([
                'jsonrpc' => '2.0',
                'result' => $tasks[$taskId],
                'id' => $request['id']
            ]) . "\n\n";
            
            exit;
            
        case 'tasks/pushNotificationConfig/set':
            if (!isset($request['params']['taskId']) || !isset($request['params']['pushNotificationConfig'])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]);
                break;
            }
            
            $taskId = $request['params']['taskId'];
            $config = $request['params']['pushNotificationConfig'];
            
            // Check if task exists
            if (!isset($tasks[$taskId])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32001, 'message' => 'Task not found'],
                    'id' => $request['id']
                ]);
                break;
            }
            
            $pushConfigs[$taskId] = $config;
            echo json_encode([
                'jsonrpc' => '2.0',
                'result' => ['taskId' => $taskId, 'pushNotificationConfig' => $config],
                'id' => $request['id']
            ]);
            break;
            
        case 'tasks/pushNotificationConfig/get':
            if (!isset($request['params']['id'])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]);
                break;
            }
            
            $taskId = $request['params']['id'];
            
            // Check if task exists
            if (!isset($tasks[$taskId])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32001, 'message' => 'Task not found'],
                    'id' => $request['id']
                ]);
                break;
            }
            
            if (isset($pushConfigs[$taskId])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'result' => ['taskId' => $taskId, 'pushNotificationConfig' => $pushConfigs[$taskId]],
                    'id' => $request['id']
                ]);
            } else {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'result' => ['taskId' => $taskId, 'pushNotificationConfig' => null],
                    'id' => $request['id']
                ]);
            }
            break;
            
        case 'tasks/pushNotificationConfig/list':
            $configs = [];
            foreach ($pushConfigs as $taskId => $config) {
                $configs[] = ['taskId' => $taskId, 'pushNotificationConfig' => $config];
            }
            echo json_encode([
                'jsonrpc' => '2.0',
                'result' => ['configs' => $configs],
                'id' => $request['id']
            ]);
            break;
            
        case 'tasks/pushNotificationConfig/delete':
            if (!isset($request['params']['id'])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32602, 'message' => 'Invalid params'],
                    'id' => $request['id']
                ]);
                break;
            }
            
            $taskId = $request['params']['id'];
            
            // Check if task exists
            if (!isset($tasks[$taskId])) {
                echo json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32001, 'message' => 'Task not found'],
                    'id' => $request['id']
                ]);
                break;
            }
            
            unset($pushConfigs[$taskId]);
            echo json_encode([
                'jsonrpc' => '2.0',
                'result' => null,
                'id' => $request['id']
            ]);
            break;
            
        default:
            echo json_encode([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32601, 'message' => 'Method not found'],
                'id' => $request['id']
            ]);
    }
    exit;
}

// Default response
http_response_code(404);
echo json_encode(['error' => 'Not found']);