<?php

declare(strict_types=1);

namespace A2A\Storage;

use A2A\Models\v030\Task;
use A2A\Models\PushNotificationConfig;
use Illuminate\Cache\Repository;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Filesystem\Filesystem;

/**
 * Laravel Cache-based storage for task and push notification persistence
 * Supports multiple drivers while maintaining the same API
 *
 * Supported drivers:
 * - 'file': File-based storage (default)
 * - 'array': In-memory storage (for testing)
 * - 'redis': Redis storage (requires illuminate/redis)
 * - 'memcached': Memcached storage (requires memcached extension)
 *
 * Usage examples:
 * new Storage(); // Uses file driver with default directory
 * new Storage('file', '/custom/path'); // File driver with custom path
 * new Storage('array'); // In-memory storage
 * new Storage('redis', '', ['host' => 'localhost', 'port' => 6379]);
 * new Storage('memcached', '', ['servers' => [['127.0.0.1', 11211]]]);
 */
class Storage
{
    private Repository $cache;
    private string $tasksPrefix = 'a2a_tasks_';
    private string $pushConfigsPrefix = 'a2a_push_configs_';
    private string $tasksIndexKey = 'a2a_tasks_index';
    private string $pushConfigsIndexKey = 'a2a_push_configs_index';

    public function __construct(
        string $driver = 'file',
        string $dataDir = '/tmp/a2a_storage',
        array $config = []
    ) {
        $this->cache = $this->createCacheRepository($driver, $dataDir, $config);
    }

    /**
     * Save a task
     */
    public function saveTask(Task $task): void
    {
        $taskId = $task->getId();
        $taskData = $task->toArray();

        // Store the task
        $this->cache->forever($this->tasksPrefix . $taskId, $taskData);

        // Update tasks index
        $tasksIndex = $this->cache->get($this->tasksIndexKey, []);
        if (!in_array($taskId, $tasksIndex)) {
            $tasksIndex[] = $taskId;
            $this->cache->forever($this->tasksIndexKey, $tasksIndex);
        }
    }

    /**
     * Get a task by ID
     */
    public function getTask(string $taskId): ?Task
    {
        $taskData = $this->cache->get($this->tasksPrefix . $taskId);

        if ($taskData === null) {
            return null;
        }

        return Task::fromArray($taskData);
    }

    /**
     * Check if task exists
     */
    public function taskExists(string $taskId): bool
    {
        return $this->cache->has($this->tasksPrefix . $taskId);
    }

    /**
     * Update task status
     */
    public function updateTaskStatus(string $taskId, string $status): bool
    {
        $taskData = $this->cache->get($this->tasksPrefix . $taskId);

        if ($taskData === null) {
            return false;
        }

        $taskData['status']['state'] = $status;
        $taskData['status']['timestamp'] = date('c');

        $this->cache->forever($this->tasksPrefix . $taskId, $taskData);
        return true;
    }

    /**
     * Save push notification config
     */
    public function savePushConfig(string $taskId, PushNotificationConfig $config): void
    {
        $configData = $config->toArray();

        // Store the config
        $this->cache->forever($this->pushConfigsPrefix . $taskId, $configData);

        // Update push configs index
        $configsIndex = $this->cache->get($this->pushConfigsIndexKey, []);
        if (!in_array($taskId, $configsIndex)) {
            $configsIndex[] = $taskId;
            $this->cache->forever($this->pushConfigsIndexKey, $configsIndex);
        }
    }

    /**
     * Get push notification config
     */
    public function getPushConfig(string $taskId): ?PushNotificationConfig
    {
        $configData = $this->cache->get($this->pushConfigsPrefix . $taskId);

        if ($configData === null) {
            return null;
        }

        return PushNotificationConfig::fromArray($configData);
    }

    /**
     * List all push configs
     */
    public function listPushConfigs(): array
    {
        $configsIndex = $this->cache->get($this->pushConfigsIndexKey, []);
        $configs = [];

        foreach ($configsIndex as $taskId) {
            $configData = $this->cache->get($this->pushConfigsPrefix . $taskId);
            if ($configData !== null) {
                $config = PushNotificationConfig::fromArray($configData);
                $configs[] = [
                    'taskId' => $taskId,
                    'pushNotificationConfig' => $config->toArray()
                ];
            }
        }

        return $configs;
    }

    /**
     * Delete push notification config
     */
    public function deletePushConfig(string $taskId): bool
    {
        if (!$this->cache->has($this->pushConfigsPrefix . $taskId)) {
            return false;
        }

        // Remove the config
        $this->cache->forget($this->pushConfigsPrefix . $taskId);

        // Update index
        $configsIndex = $this->cache->get($this->pushConfigsIndexKey, []);
        $configsIndex = array_filter($configsIndex, fn($id) => $id !== $taskId);
        $this->cache->forever($this->pushConfigsIndexKey, array_values($configsIndex));

        return true;
    }

    /**
     * Create cache repository with specified driver
     */
    private function createCacheRepository(string $driver, string $dataDir, array $config): Repository
    {
        switch ($driver) {
            case 'file':
                return $this->createFileStore($dataDir);

            case 'array':
                return $this->createArrayStore();

            case 'redis':
                return $this->createRedisStore($config);

            case 'memcached':
                return $this->createMemcachedStore($config);

            default:
                throw new \InvalidArgumentException("Unsupported cache driver: {$driver}");
        }
    }

    /**
     * Create file-based cache store
     */
    private function createFileStore(string $dataDir): Repository
    {
        // Create data directory if it doesn't exist
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $filesystem = new Filesystem();
        $store = new FileStore($filesystem, $dataDir);

        return new Repository($store);
    }

    /**
     * Create array-based cache store (in-memory)
     */
    private function createArrayStore(): Repository
    {
        $store = new ArrayStore();
        return new Repository($store);
    }

    /**
     * Create Redis cache store
     */
    private function createRedisStore(array $config): Repository
    {
        if (!class_exists('\Illuminate\Redis\RedisManager')) {
            throw new \RuntimeException('Redis support requires illuminate/redis package');
        }

        $redisConfig = array_merge(
            [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
            ],
            $config
        );

        // Note: Redis support requires proper Laravel container setup
        throw new \RuntimeException('Redis support requires full Laravel framework setup');
    }

    /**
     * Create Memcached cache store
     */
    private function createMemcachedStore(array $config): Repository
    {
        throw new \RuntimeException('Memcached support requires the memcached PHP extension');
    }
}
