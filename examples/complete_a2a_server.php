<?php

declare(strict_types=1);

/**
 * Complete A2A Protocol Server Implementation
 * 
 * This server provides a full implementation of the A2A Protocol v0.3.0
 * with enhanced task management, streaming capabilities, and comprehensive
 * error handling for complete TCK compliance.
 * 
 * Features:
 * - Complete A2A Protocol v0.3.0 compliance
 * - JSON-RPC 2.0 transport with proper error codes
 * - Task lifecycle management with custom ID support
 * - Event-driven architecture for real-time updates
 * - Server-Sent Events (SSE) for streaming
 * - Comprehensive error handling and validation
 * 
 * Usage:
 *   php -S localhost:8080 complete_a2a_server.php
 */

// Keep PHP warnings/notices out of the HTTP response body: anything printed
// before the JSON-RPC payload corrupts the protocol stream for strict clients.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use A2A\A2AServer;
use A2A\A2AProtocol_v030;
use A2A\TaskManager;
use A2A\Models\v030\AgentCard;
use A2A\Models\AgentCapabilities;
use A2A\Models\v030\Message;
use A2A\Events\EventBusManager;
use A2A\Execution\DefaultAgentExecutor;
use A2A\PushNotificationManager;
use A2A\Storage\Storage;
use A2A\Streaming\StreamingServer;
use A2A\Utils\JsonRpc;
use A2A\Exceptions\A2AErrorCodes;
use Psr\Log\LoggerInterface;

/**
 * Enhanced Logger for A2A Server Operations
 */
class A2AServerLogger implements LoggerInterface
{
    private string $logFile;

    public function __construct(string $logFile = 'a2a_server.log')
    {
        $this->logFile = $logFile;
    }

    public function emergency($message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Never output to console when running as web server to avoid JSON contamination
        // Only log to file for production web server usage
    }
}

/**
 * Enhanced A2A Server with Complete Integration
 */
class CompleteA2AServer
{
    private A2AServer $server;
    private TaskManager $taskManager;
    private PushNotificationManager $pushNotificationManager;
    private EventBusManager $eventBusManager;
    private DefaultAgentExecutor $executor;
    private StreamingServer $streamingServer;
    private A2AServerLogger $logger;
    private AgentCard $agentCard;
    private Storage $sharedStorage;

    public function __construct()
    {
        $this->logger = new A2AServerLogger();
        $this->initializeComponents();
        $this->setupAgentCard();
        $this->setupMessageHandlers();
    }

    private function initializeComponents(): void
    {
        $storagePath = '/tmp/a2a_complete_example_storage';
        $this->sharedStorage = new Storage('file', $storagePath);
        $this->taskManager = new TaskManager($this->sharedStorage);
        $this->pushNotificationManager = new PushNotificationManager($this->sharedStorage);
        $this->eventBusManager = new EventBusManager();
        $this->executor = new DefaultAgentExecutor();
        $this->streamingServer = new StreamingServer();

        $this->logger->info('A2A Server components initialized');
    }

    private function setupAgentCard(): void
    {
        // Create simple agent capabilities for basic functionality
        $capabilities = new AgentCapabilities(
            true,  // streaming
            true,  // pushNotifications  
            false, // stateTransitionHistory
            []     // extensions
        );

        // Create agent card with all required parameters
        $skills = [
            new \A2A\Models\AgentSkill(
                'text-processing',
                'Text Processing',
                'Process and respond to text-based requests',
                ['text', 'general']
            ),
            new \A2A\Models\AgentSkill(
                'file-processing',
                'File Processing',
                'Process and analyze file-based content',
                ['file', 'data']
            )
        ];

        $this->agentCard = new AgentCard(
            'complete-a2a-server',                    // name
            'Complete A2A Server Implementation',     // description
            'http://localhost:8081',                  // url
            '1.0.0',                                  // version
            $capabilities,                            // capabilities
            ['text', 'file', 'data'],                // defaultInputModes
            ['text', 'file', 'data'],                // defaultOutputModes
            $skills                                   // skills (at least one required)
        );

        $this->agentCard->setSupportsAuthenticatedExtendedCard(true);

        // Initialize server with enhanced components and shared TaskManager
        // Enable A2A Protocol compliance mode for TCK tests
        $protocol = new A2AProtocol_v030(
            $this->agentCard,
            null,
            $this->logger,
            $this->taskManager,
            $this->pushNotificationManager,
            $this->eventBusManager,
            $this->executor,
            $this->streamingServer
        );
        $this->server = new A2AServer($protocol, $this->logger);

        $this->logger->info('Agent card configured', [
            'agent_id' => $this->agentCard->getName(),
            'version' => $this->agentCard->getVersion()
        ]);
    }

    private function setupMessageHandlers(): void
    {
        // Add comprehensive message handler with task integration
        $messageHandler = new class($this->logger, $this->taskManager) implements \A2A\Interfaces\MessageHandlerInterface {
            private $logger;
            private $taskManager;
            
            public function __construct($logger, $taskManager) {
                $this->logger = $logger;
                $this->taskManager = $taskManager;
            }
            
            public function canHandle(Message $message): bool {
                return true;
            }
            
            public function handle(Message $message, string $fromAgent): array {
                $this->logger->info('Processing message', [
                    'from' => $fromAgent,
                    'message_id' => $message->getMessageId(),
                    'role' => $message->getRole()
                ]);

                // The A2AServer handles task state progression automatically
                // via the progressTaskState method - no manual override needed
                $taskId = $message->getTaskId();
                $interactionCount = 1;
                $state = 'working';
                if ($taskId) {
                    $task = $this->taskManager->getTask($taskId);
                    if ($task) {
                        $interactionCount = count($task->getHistory());
                        if ($interactionCount > 1) {
                            $state = 'completed';
                        }
                        $this->logger->info('Message task ready for interaction', [
                            'task_id' => $taskId,
                            'message_id' => $message->getMessageId(),
                            'state' => $task->getStatus()->getState()->value
                        ]);
                    }
                }
                
                return [
                    'status' => [
                        'state' => $state,
                        'timestamp' => date('c')
                    ],
                    'metadata' => [
                        'message' => $state === 'completed' ? 'Request processed successfully' : 'Task is still in progress',
                        'interactionCount' => $interactionCount
                    ]
                ];
            }
        };
        
        $this->server->addMessageHandler($messageHandler);
    }

    public function handleRequest(): void
    {
        // Set CORS headers for cross-origin requests
        $this->setCorsHeaders();

        // Handle preflight requests
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(200);
            return;
        }

        // Only accept POST requests for A2A protocol
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->sendErrorResponse('Method not allowed', 405);
            return;
        }

        // Get request body
        $input = file_get_contents('php://input');
        if ($input === false || empty($input)) {
            $this->sendErrorResponse('Empty request body', 400);
            return;
        }

        // Parse JSON request
        $request = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendJsonRpcError(null, 'Parse error', A2AErrorCodes::PARSE_ERROR);
            return;
        }

        $this->logger->info('Request received', [
            'method' => $request['method'] ?? 'unknown',
            'id' => $request['id'] ?? 'none'
        ]);

        // Handle streaming requests through A2AServer for proper validation
        if (isset($request['method']) && $request['method'] === 'message/stream') {
            try {
                $response = $this->server->handleRequest($request);
                if (empty($response)) {
                    return;
                }
                $this->sendJsonResponse($response);
                return;
            } catch (\Exception $e) {
                $this->sendJsonRpcError(
                    $request['id'] ?? null,
                    'Streaming error: ' . $e->getMessage(),
                    A2AErrorCodes::INTERNAL_ERROR
                );
                return;
            }
        }

        // Process regular requests through enhanced A2AServer
        try {
            $response = $this->server->handleRequest($request);
            $this->sendJsonResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Request processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->sendJsonRpcError(
                $request['id'] ?? null,
                'Internal server error: ' . $e->getMessage(),
                A2AErrorCodes::INTERNAL_ERROR
            );
        }
    }

    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
    }

    private function sendJsonResponse(array $response): void
    {
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sendErrorResponse(string $message, int $httpCode): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $message,
            'code' => $httpCode
        ]);
    }

    private function sendJsonRpcError(?string $id, string $message, int $code): void
    {
        $jsonRpc = new JsonRpc();
        $error = $jsonRpc->createError($id, $message, $code);
        $this->sendJsonResponse($error);
    }

    public function getAgentCard(): array
    {
        return $this->server->getAgentCard()->toArray();
    }

    public function getServerInfo(): array
    {
        return [
            'server' => 'Complete A2A Server',
            'version' => '1.0.0',
            'protocol' => 'A2A v0.3.0',
            'transport' => 'JSON-RPC 2.0',
            'features' => [
                'task_management' => true,
                'streaming' => true,
                'event_bus' => true,
                'comprehensive_errors' => true,
                'tck_compliant' => true
            ],
            'agent_card' => $this->server->getAgentCard()->toArray(),
            'endpoints' => [
                'message/send' => 'Send message to agent',
                'message/stream' => 'Stream message processing',
                'get_agent_card' => 'Get agent metadata',
                'tasks/get' => 'Get task status',
                'tasks/cancel' => 'Cancel running task',
                'ping' => 'Health check'
            ]
        ];
    }
}

// Initialize and run the complete A2A server
$server = new CompleteA2AServer();

// Check if running from CLI or via web server
if (php_sapi_name() === 'cli') {
    echo "Complete A2A Server v1.0.0 starting on http://localhost:8081\n";
    echo "Agent: {$server->getAgentCard()['name']}\n";
    echo "Protocol: A2A v{$server->getAgentCard()['protocolVersion']}\n";
    echo "Press Ctrl+C to stop\n\n";

    // Start built-in PHP web server
    $command = 'php -S localhost:8081 -t ' . __DIR__ . ' ' . __FILE__;
    passthru($command);
    exit;
}

// Web server handling starts here
// Handle special info endpoint for server metadata
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' &&
    (($_SERVER['REQUEST_URI'] ?? '') === '/info' || ($_SERVER['REQUEST_URI'] ?? '') === '/?info')
) {
    header('Content-Type: application/json');
    echo json_encode($server->getServerInfo(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Handle A2A well-known agent card endpoint
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' &&
    ($_SERVER['REQUEST_URI'] ?? '') === '/.well-known/agent-card.json'
) {
    header('Content-Type: application/json');
    echo json_encode($server->getAgentCard(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle A2A protocol requests
$server->handleRequest();