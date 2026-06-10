<?php

declare(strict_types=1);

namespace A2A\Notifications;

use A2A\Models\v030\Task;
use A2A\PushNotificationManager;
use A2A\Utils\HttpClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Delivers task snapshots to configured push notification webhooks
 * (A2A v0.3.0 §9.5) and enforces the optional webhook host allowlist.
 */
class PushNotifier
{
    private PushNotificationManager $pushNotificationManager;
    private HttpClient $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        PushNotificationManager $pushNotificationManager,
        ?HttpClient $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->pushNotificationManager = $pushNotificationManager;
        $this->httpClient = $httpClient ?? new HttpClient();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Deliver the current task snapshot to the webhook configured for the
     * task, if any. Delivery failures are logged and never interrupt
     * request handling.
     */
    public function notify(Task $task): void
    {
        $config = $this->pushNotificationManager->getConfig($task->getId());
        if ($config === null) {
            return;
        }

        $url = $config->getUrl();

        if (!$this->isWebhookUrlAllowed($url)) {
            $this->logger->warning('Push notification skipped: webhook host not in allowlist', [
                'task_id' => $task->getId(),
                'url' => $url
            ]);
            return;
        }

        $headers = [];

        if ($config->getToken() !== null && $config->getToken() !== '') {
            $headers['X-A2A-Notification-Token'] = $config->getToken();
        }

        $authentication = $config->getAuthentication();
        if ($authentication !== null) {
            $authData = $authentication->toArray();
            $schemes = $authData['schemes'] ?? [];
            $credentials = $authData['credentials'] ?? null;
            if (!empty($schemes) && is_string($credentials) && $credentials !== '') {
                $headers['Authorization'] = $schemes[0] . ' ' . $credentials;
            }
        }

        try {
            $this->httpClient->postNotification($url, $task->toArray(), $headers);
            $this->logger->info('Push notification delivered', [
                'task_id' => $task->getId(),
                'state' => $task->getStatus()->getState()->value
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Push notification delivery failed', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Enforce the optional A2A_WEBHOOK_ALLOWLIST env var (comma-separated
     * hostnames). When unset or empty, every webhook URL is allowed, which
     * preserves the pre-allowlist behavior.
     */
    public function isWebhookUrlAllowed(string $url): bool
    {
        $allowList = getenv('A2A_WEBHOOK_ALLOWLIST');
        if ($allowList === false || trim($allowList) === '') {
            return true;
        }

        $allowedHosts = array_map('trim', explode(',', $allowList));
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && in_array($host, $allowedHosts, true);
    }
}
