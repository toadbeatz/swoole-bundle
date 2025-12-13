# Complete Features Documentation / Documentation Complète des Fonctionnalités

🇬🇧 **[English](#english)** | 🇫🇷 **[Français](#français)**

---

# English

This document details all Swoole 6.1.4 features exploited in this bundle to maximize Symfony 7/8 performance.

## 📊 Overview

| Feature | Status | Performance Gain |
|---------|--------|------------------|
| **MySQL Connection Pool** | ✅ | 10-100x |
| **PostgreSQL Connection Pool** | ✅ | 10-100x |
| **Redis Connection Pool** | ✅ | 5-20x |
| **Task Workers** | ✅ | 100-1000x |
| **Scheduler/Timer** | ✅ | ∞ (async) |
| **Lock/Mutex** | ✅ | Thread-safe |
| **Atomic Operations** | ✅ | 1000x+ |
| **Queue System** | ✅ | 100-1000x |
| **Rate Limiter** | ✅ | Protection |
| **Metrics Collector** | ✅ | Monitoring |
| **Cache Swoole Table** | ✅ | 1000-10000x |
| **Sessions Swoole Table** | ✅ | 1000-10000x |
| **HTTP/2 Client** | ✅ | Multiplexing |
| **Advanced Coroutines** | ✅ | Non-blocking |
| **Async FileSystem** | ✅ | Non-blocking |
| **Thread Pool (6.1)** | ✅ | CPU-intensive |
| **Process Manager** | ✅ | Parallel workers |
| **Async Socket** | ✅ | Non-blocking |
| **Async DNS** | ✅ | Non-blocking |

---

## 1. Database Connection Pools

### MySQL Pool

High-performance MySQL connections using Swoole Coroutine MySQL.

```php
use Toadbeatz\SwooleBundle\Database\ConnectionPool;

// Create pool
$pool = new ConnectionPool([
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'password' => 'password',
    'database' => 'myapp',
    'charset' => 'utf8mb4',
], poolSize: 10, timeout: 5.0);

// Get connection and execute query
$connection = $pool->get();
try {
    $result = $connection->query('SELECT * FROM users WHERE id = 1');
    // Process result...
} finally {
    $pool->put($connection); // Return to pool
}

// Get statistics
$stats = $pool->getStats();
// ['size' => 10, 'current' => 5, 'available' => 3, 'in_use' => 2]
```

### PostgreSQL Pool

High-performance PostgreSQL connections using Swoole Coroutine PostgreSQL.

```php
use Toadbeatz\SwooleBundle\Database\PostgreSQLPool;

$pool = new PostgreSQLPool([
    'host' => '127.0.0.1',
    'port' => 5432,
    'database' => 'myapp',
    'user' => 'postgres',
    'password' => 'password',
], poolSize: 10, timeout: 5.0);

// Simple query with parameters
$users = $pool->query('SELECT * FROM users WHERE active = $1', [true]);

// Execute statement (INSERT, UPDATE, DELETE)
$affected = $pool->execute(
    'INSERT INTO users (name, email) VALUES ($1, $2)',
    ['John', 'john@example.com']
);

// Transaction
$conn = $pool->beginTransaction();
try {
    $conn->query('INSERT INTO orders ...');
    $conn->query('UPDATE inventory ...');
    $pool->commit($conn);
} catch (\Exception $e) {
    $pool->rollback($conn);
    throw $e;
}
```

### Redis Pool

High-performance Redis connections using Swoole Coroutine Redis.

```php
use Toadbeatz\SwooleBundle\Database\RedisPool;

$pool = new RedisPool([
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => 'secret',
    'database' => 0,
], poolSize: 20, timeout: 3.0);

// Basic operations
$pool->set('user:1:name', 'John', ttl: 3600);
$name = $pool->get_value('user:1:name');
$pool->delete('user:1:name');

// Check existence
if ($pool->exists('user:1:name')) {
    // ...
}

// Counters
$pool->incr('page:views');
$pool->decr('stock:item:1');

// Hash operations
$pool->hset('user:1', 'name', 'John');
$pool->hset('user:1', 'email', 'john@example.com');
$name = $pool->hget('user:1', 'name');
$user = $pool->hgetall('user:1');

// List operations
$pool->lpush('queue:emails', 'email1', 'email2');
$email = $pool->rpop('queue:emails');

// Expiration
$pool->expire('session:abc', 1800);
$ttl = $pool->ttl('session:abc');

// Pipeline (batch operations)
$results = $pool->pipeline(function ($pipe) {
    $pipe->set('key1', 'value1');
    $pipe->set('key2', 'value2');
    $pipe->get('key1');
});
```

---

## 2. Advanced Coroutines (Swoole 6.1)

### Parallel Execution

Execute multiple operations concurrently.

```php
use Toadbeatz\SwooleBundle\Coroutine\CoroutineHelper;

// Execute in parallel
$results = CoroutineHelper::parallel([
    fn() => $userService->fetchUser($id),
    fn() => $productService->fetchProducts(),
    fn() => $orderService->fetchOrders($userId),
]);
// $results = [user, products, orders]

// Parallel with WaitGroup (better synchronization)
$results = CoroutineHelper::parallelWait([
    'user' => fn() => $userService->fetchUser($id),
    'products' => fn() => $productService->fetchProducts(),
]);
// $results = ['user' => ..., 'products' => ...]
```

### Race & Timeout

```php
// Race - First successful result wins
$result = CoroutineHelper::race([
    fn() => $this->fetchFromPrimaryServer(),
    fn() => $this->fetchFromBackupServer(),
], timeout: 5.0);

// Timeout wrapper
$result = CoroutineHelper::withTimeout(
    fn() => $this->longRunningOperation(),
    timeout: 10.0
);
// Throws RuntimeException if timeout exceeded
```

### Retry & Circuit Breaker

```php
// Retry with exponential backoff
$result = CoroutineHelper::retry(
    fn() => $this->unreliableApiCall(),
    maxAttempts: 5,        // Max retry attempts
    initialDelay: 0.1,     // Initial delay (seconds)
    maxDelay: 5.0,         // Max delay between retries
    multiplier: 2.0        // Exponential multiplier
);

// Circuit Breaker pattern
$result = CoroutineHelper::withCircuitBreaker(
    fn() => $this->externalService->call(),
    name: 'payment_gateway',    // Circuit name
    failureThreshold: 5,        // Failures before opening
    recoveryTimeout: 30.0       // Seconds before half-open
);
// Throws RuntimeException if circuit is open
```

### Coroutine Management

```php
// Create coroutine
$cid = CoroutineHelper::create(fn() => doSomething());

// Cancel coroutine (Swoole 6.1 feature)
CoroutineHelper::cancel($cid, throwException: true);

// Check if cancelled
if (CoroutineHelper::isCancelled()) {
    // Cleanup and exit
}

// Yield current coroutine
CoroutineHelper::yield();

// Resume yielded coroutine
CoroutineHelper::resume($cid);

// Defer execution (runs when coroutine exits)
CoroutineHelper::defer(fn() => $this->cleanup());

// Get current coroutine ID
$currentCid = CoroutineHelper::getCid();

// Get parent coroutine ID
$parentCid = CoroutineHelper::getPcid();

// Check if in coroutine context
if (CoroutineHelper::inCoroutine()) {
    // In coroutine
}

// Get statistics
$stats = CoroutineHelper::getStats();
// ['coroutine_num' => 42, 'coroutine_peak_num' => 100, ...]

// Get all coroutine IDs
$cids = CoroutineHelper::listCoroutines();
```

---

## 3. HTTP/2 Client

High-performance HTTP/2 client with multiplexing support.

```php
use Toadbeatz\SwooleBundle\Http\Http2Client;

$client = new Http2Client('api.example.com', 443, ssl: true, timeout: 5.0);

// Set custom settings
$client->setSettings([
    'timeout' => 10.0,
    'ssl_verify_peer' => false,
]);

// Connect
$client->connect();

// Simple requests
$response = $client->get('/users');
$response = $client->post('/users', '{"name":"John"}', ['content-type' => 'application/json']);
$response = $client->postJson('/users', ['name' => 'John']);
$response = $client->put('/users/1', '{"name":"Jane"}');
$response = $client->delete('/users/1');

// Multiplexing - Send multiple requests in parallel
$responses = $client->sendMultiple([
    ['method' => 'GET', 'path' => '/users'],
    ['method' => 'GET', 'path' => '/products'],
    ['method' => 'POST', 'path' => '/orders', 'body' => '{"item": 1}', 'headers' => ['content-type' => 'application/json']],
]);

// Use response
if ($response->isOk()) {
    $statusCode = $response->getStatusCode();
    $headers = $response->getHeaders();
    $body = $response->getBody();
    $data = $response->toArray(); // JSON decode
}

// Check response type
$response->isRedirect();    // 3xx
$response->isClientError(); // 4xx
$response->isServerError(); // 5xx

// Close connection
$client->close();
```

---

## 4. Async FileSystem

Non-blocking file operations using Swoole coroutines.

```php
use Toadbeatz\SwooleBundle\FileSystem\AsyncFileSystem;

// Read/Write files
$content = AsyncFileSystem::readFile('/path/to/file.txt');
AsyncFileSystem::writeFile('/path/to/output.txt', $content);
AsyncFileSystem::appendFile('/path/to/log.txt', "Log entry\n");

// JSON operations
$config = AsyncFileSystem::readJson('/path/to/config.json');
AsyncFileSystem::writeJson('/path/to/output.json', $data, JSON_PRETTY_PRINT);

// File operations
AsyncFileSystem::copy('/source.txt', '/dest.txt');
AsyncFileSystem::rename('/old.txt', '/new.txt');
AsyncFileSystem::unlink('/file.txt');

// Directory operations
AsyncFileSystem::mkdir('/path/to/dir', 0755, recursive: true);
AsyncFileSystem::rmdir('/path/to/dir', recursive: true);

// File info
$exists = AsyncFileSystem::exists('/path/to/file.txt');
$stat = AsyncFileSystem::stat('/path/to/file.txt');
$size = AsyncFileSystem::fileSize('/path/to/file.txt');

// Read file line by line (generator)
foreach (AsyncFileSystem::readLines('/path/to/file.txt') as $line) {
    // Process line
}

// Execute shell command
$result = AsyncFileSystem::exec('ls -la /path');
// ['code' => 0, 'output' => '...', 'signal' => 0]

// Wait for file change (like inotify)
$changed = AsyncFileSystem::waitFileChange('/path/to/file.txt', timeout: 30.0);
```

---

## 5. Thread Pool (Swoole 6.1)

CPU-intensive task execution using native threads.

```php
use Toadbeatz\SwooleBundle\Thread\ThreadPool;

// Check availability
if (ThreadPool::isAvailable()) {
    $pool = new ThreadPool(size: 4);
    $pool->initialize();

    // Submit task (async)
    $taskId = $pool->submit(
        fn($data, $shared) => heavyComputation($data),
        $inputData
    );

    // Get result (blocking)
    $result = $pool->getResult($taskId, timeout: 30.0);

    // Or combined (submit + wait)
    $result = $pool->execute(
        fn($data) => processImage($data),
        $imageData,
        timeout: 60.0
    );

    // Shared data between threads
    $pool->setShared('config', ['key' => 'value']);
    $config = $pool->getShared('config');

    // Statistics
    $stats = $pool->getStats();
    // ['size' => 4, 'running' => true, 'pending_tasks' => 2, ...]

    // Shutdown
    $pool->shutdown();
}

// Get active thread count
$activeCount = ThreadPool::activeCount();
```

---

## 6. Process Manager

Manage child processes for parallel execution.

```php
use Toadbeatz\SwooleBundle\Process\ProcessManager;
use Toadbeatz\SwooleBundle\Process\WorkerPool;

$manager = new ProcessManager();

// Create single process
$pid = $manager->create(function ($worker) {
    while (true) {
        // Worker logic
        $data = $worker->read(); // Read from parent
        // Process...
        sleep(1);
    }
});

// Create multiple workers
$pids = $manager->createWorkers(4, function ($worker, $workerId) {
    echo "Worker {$workerId} started\n";
    // Worker logic
});

// Inter-process communication
$manager->write($pid, "Message from parent");
$response = $manager->read($pid);

// Send signal
$manager->signal($pid, SIGTERM);

// Wait for process
$result = $manager->wait($pid);

// Wait for all processes
$results = $manager->waitAll();

// Kill all processes
$manager->killAll(SIGTERM);

// Register signal handler
ProcessManager::registerSignal(SIGTERM, function () {
    echo "Received SIGTERM\n";
    exit(0);
});

// Set CPU affinity
$manager->setAffinity($pid, [0, 1]); // Bind to CPU 0 and 1

// Statistics
$stats = $manager->getStats();

// --- Worker Pool ---

$pool = new WorkerPool(workerCount: 4);

$pool->start(function ($worker, $workerId) {
    // Worker logic
    while (true) {
        // Process tasks
    }
});

// Get active count
$active = $pool->getActiveCount();

// Stop all
$pool->stop();

// Restart all
$pool->restart($callback);
```

---

## 7. Async Socket & DNS

### Async Socket

```php
use Toadbeatz\SwooleBundle\Socket\AsyncSocket;

// Create TCP socket
$socket = new AsyncSocket(AsyncSocket::TYPE_TCP, timeout: 5.0);

// Connect
$socket->connect('api.example.com', 80);

// Send data
$socket->send("GET / HTTP/1.1\r\nHost: api.example.com\r\n\r\n");

// Receive data
$response = $socket->recv(65535);

// Receive line (until newline)
$line = $socket->recvLine();

// Request-response pattern
$response = $socket->sendRecv($request);

// Check connection
if ($socket->isConnected()) {
    // ...
}

// Get errors
$errorCode = $socket->getErrorCode();
$errorMsg = $socket->getErrorMessage();

// Close
$socket->close();

// Available socket types:
// AsyncSocket::TYPE_TCP      - TCP IPv4
// AsyncSocket::TYPE_UDP      - UDP IPv4
// AsyncSocket::TYPE_TCP6     - TCP IPv6
// AsyncSocket::TYPE_UDP6     - UDP IPv6
// AsyncSocket::TYPE_UNIX_STREAM - Unix stream
// AsyncSocket::TYPE_UNIX_DGRAM  - Unix datagram
```

### Async DNS

```php
use Toadbeatz\SwooleBundle\Socket\AsyncDNS;

// Resolve hostname (non-blocking)
$ip = AsyncDNS::resolve('example.com', AF_INET, timeout: 5.0);

// Get all IPs
$ips = AsyncDNS::resolveAll('example.com');

// Reverse lookup
$hostname = AsyncDNS::reverse('93.184.216.34');

// Validation helpers
AsyncDNS::isValidIp('192.168.1.1');      // true
AsyncDNS::isValidIpv4('192.168.1.1');    // true
AsyncDNS::isValidIpv6('::1');            // true
AsyncDNS::isValidIpv6('192.168.1.1');    // false
```

---

## 8. Metrics & Monitoring

```php
use Toadbeatz\SwooleBundle\Metrics\MetricsCollector;

// Metrics are automatically recorded by HttpServerManager

// Get metrics
$metrics = $collector->getMetrics();
/*
[
    'requests' => [
        'total' => 10000,
        'errors' => 50,
        'success' => 9950,
        'error_rate' => 0.5
    ],
    'performance' => [
        'avg_response_time_ms' => 12.5,
        'total_response_time_ms' => 125000,
        'requests_per_second' => 150.5
    ],
    'network' => [
        'bytes_received' => 1000000,
        'bytes_sent' => 5000000,
        'bytes_received_human' => '976.56 KB',
        'bytes_sent_human' => '4.77 MB'
    ],
    'server' => [
        'connection_num' => 100,
        'worker_num' => 4,
        'task_worker_num' => 2,
        'idle_worker_num' => 2,
        'coroutine_num' => 50,
        ...
    ],
    'memory' => [
        'usage' => 50000000,
        'peak' => 80000000,
        'usage_human' => '47.68 MB',
        'peak_human' => '76.29 MB'
    ]
]
*/

// Export for Prometheus
$prometheus = $collector->exportPrometheus();
/*
# HELP swoole_requests_total Total number of requests
# TYPE swoole_requests_total counter
swoole_requests_total 10000
# HELP swoole_errors_total Total number of errors
# TYPE swoole_errors_total counter
swoole_errors_total 50
...
*/

// Export as JSON
$json = $collector->exportJson();

// Reset metrics
$collector->reset();
```

---

## 9. Rate Limiter

Token bucket algorithm implementation using Swoole Table.

```php
use Toadbeatz\SwooleBundle\RateLimiter\RateLimiter;

$limiter = new RateLimiter(
    maxRequests: 100,     // Max requests per window
    windowSeconds: 60     // Window duration
);

// Check if allowed
if (!$limiter->isAllowed($clientIp)) {
    throw new TooManyRequestsException('Rate limit exceeded');
}

// Get remaining requests
$remaining = $limiter->getRemaining($clientIp);

// Get full info
$info = $limiter->getInfo($clientIp);
/*
[
    'allowed' => true,
    'remaining' => 95,
    'reset_at' => 1702400000,
    'current_count' => 5
]
*/

// Reset for identifier
$limiter->reset($clientIp);
```

---

## 10. Task Workers & Scheduler

### Task Workers

Offload heavy tasks to dedicated worker processes.

```php
use Toadbeatz\SwooleBundle\Task\TaskWorker;
use Toadbeatz\SwooleBundle\Task\TaskData;

// Register task handlers
$taskWorker->registerHandler('send_email', function ($data) {
    $mailer->send($data['to'], $data['subject'], $data['body']);
    return ['status' => 'sent', 'to' => $data['to']];
});

$taskWorker->registerHandler('process_image', function ($data) {
    $image = Image::load($data['path']);
    $image->resize($data['width'], $data['height']);
    $image->save($data['output']);
    return ['status' => 'processed'];
});

// Dispatch async (fire and forget)
$taskId = $taskWorker->dispatch(new TaskData('send_email', [
    'to' => 'user@example.com',
    'subject' => 'Welcome',
    'body' => 'Hello!',
]));

// Dispatch and wait for result
$result = $taskWorker->dispatchSync(
    new TaskData('process_image', [
        'path' => '/uploads/image.jpg',
        'width' => 800,
        'height' => 600,
        'output' => '/processed/image.jpg',
    ]),
    timeout: 30.0
);
```

### Scheduler

Cron-like task scheduling using Swoole Timer.

```php
use Toadbeatz\SwooleBundle\Task\Scheduler;

// Periodic task (every 60 seconds)
$scheduler->schedule(
    'cache_cleanup',
    fn() => $cache->cleanup(),
    interval: 60.0,
    immediate: false  // Don't run immediately
);

// Run immediately then periodically
$scheduler->schedule(
    'health_check',
    fn() => $monitor->ping(),
    interval: 5.0,
    immediate: true
);

// One-time delayed task
$scheduler->scheduleOnce(
    'send_reminder',
    fn() => $mailer->sendReminder($userId),
    delay: 300.0  // 5 minutes
);

// Cancel task
$scheduler->unschedule('cache_cleanup');

// Get scheduled tasks
$tasks = $scheduler->getScheduledTasks();
// ['cache_cleanup', 'health_check']

// Clear all
$scheduler->clearAll();
```

---

## 11. Lock & Atomic Operations

### Lock/Mutex

Thread-safe synchronization primitives.

```php
use Toadbeatz\SwooleBundle\Lock\SwooleLock;

// Create mutex lock
$lock = new SwooleLock(SwooleLock::TYPE_MUTEX);

// Manual lock/unlock
$lock->lock();
try {
    // Critical section
    $counter++;
} finally {
    $lock->unlock();
}

// Non-blocking trylock
if ($lock->trylock()) {
    try {
        // Critical section
    } finally {
        $lock->unlock();
    }
} else {
    // Lock not available
}

// Synchronized (RAII pattern - recommended)
$result = $lock->synchronized(function () use ($counter) {
    // Automatically locked/unlocked
    return $counter++;
});

// Available lock types:
// SwooleLock::TYPE_MUTEX    - Mutual exclusion
// SwooleLock::TYPE_RWLOCK   - Read-write lock
// SwooleLock::TYPE_SPINLOCK - Spin lock
// SwooleLock::TYPE_SEM      - Semaphore
```

### Atomic Operations

Lock-free thread-safe counters.

```php
use Toadbeatz\SwooleBundle\Atomic\SwooleAtomic;

$counter = new SwooleAtomic(initialValue: 0);

// Get/Set
$value = $counter->get();
$counter->set(100);

// Increment/Decrement
$counter->increment();      // +1
$counter->decrement();      // -1
$counter->add(10);          // +10
$counter->sub(5);           // -5

// Compare and swap (CAS)
$success = $counter->compareAndSwap(
    cmpVal: 100,   // Expected value
    newVal: 200    // New value if match
);

// Wait until non-zero
$success = $counter->wait(timeout: 5.0);

// Wake up waiting processes
$counter->wakeup(count: 1);
```

---

## 12. Cache & Sessions

### Swoole Table Cache

Ultra-fast in-memory cache shared between workers.

```php
use Toadbeatz\SwooleBundle\Cache\SwooleCacheAdapter;

// Via dependency injection
public function __construct(private SwooleCacheAdapter $cache) {}

// Get with callback (cache-aside pattern)
$user = $this->cache->get("user_{$id}", function ($item) use ($id) {
    $item->expiresAfter(3600); // 1 hour TTL
    return $this->userRepository->find($id);
});

// Delete
$this->cache->delete("user_{$id}");
```

### Swoole Session Handler

Configure in `config/packages/framework.yaml`:

```yaml
framework:
    session:
        handler_id: Toadbeatz\SwooleBundle\Session\SwooleSessionHandler
```

---

## 13. Server Management Commands

### Start Server

Start the Swoole HTTP server:

```bash
# Production mode
php bin/console swoole:server:start

# With custom host/port
php bin/console swoole:server:start --host=127.0.0.1 --port=8080
```

### Stop Server

Stop the running server:

```bash
php bin/console swoole:server:stop

# Force kill
php bin/console swoole:server:stop --force
```

### Reload Server (Zero-Downtime)

**Production-ready reload command** - Reload workers gracefully to apply code changes without stopping the server:

```bash
# Standard reload (clears cache + reloads workers)
php bin/console swoole:server:reload

# Skip cache clearing
php bin/console swoole:server:reload --no-cache-clear

# Only clear cache without reloading workers
php bin/console swoole:server:reload --only-cache

# Force OPcache clearing
php bin/console swoole:server:reload --opcache
```

**How it works:**
1. Clears Symfony cache (`var/cache`)
2. Clears OPcache to ensure fresh code loading
3. Sends reload signal (SIGUSR1) to all workers
4. Workers finish current requests gracefully
5. Workers reload with new code (zero downtime)

**Perfect for production deployments!** After deploying new code, simply run the reload command to apply changes without service interruption.

**Example Production Workflow:**

```bash
# 1. Deploy your code
git pull origin main
composer install --no-dev --optimize-autoloader

# 2. Reload workers (zero downtime!)
php bin/console swoole:server:reload

# Done! New code is active without any interruption 🎉
```

### Hot Reload (Development)

Start server with automatic file watching and reload:

```bash
php bin/console swoole:server:watch

# Custom poll interval
php bin/console swoole:server:watch --poll-interval=500

# Don't clear cache on reload
php bin/console swoole:server:watch --no-clear-cache
```

The hot-reload feature automatically watches for file changes in `src/`, `config/`, and `templates/` directories and reloads workers when changes are detected.

---

## Complete Configuration Reference

```yaml
swoole:
    # HTTP Server
    http:
        host: '0.0.0.0'
        port: 9501
        options:
            open_http2_protocol: true
            open_websocket_protocol: false
            enable_static_handler: true
            document_root: '%kernel.project_dir%/public'
            static_handler_locations:
                - /assets
                - /bundles

    # HTTPS/SSL
    https:
        enabled: false
        port: 9502
        cert: ~
        key: ~
        ca: ~
        verify_peer: false
        protocols: ~  # TLSv1.2 | TLSv1.3

    # HTTP/2 Settings
    http2:
        header_table_size: 4096
        initial_window_size: 65535
        max_concurrent_streams: 128
        max_frame_size: 16384
        max_header_list_size: 4096

    # Hot Reload
    hot_reload:
        enabled: true
        watch:
            - src
            - config
            - templates
        interval: 500

    # Performance
    performance:
        worker_num: ~
        max_request: 10000
        enable_coroutine: true
        max_coroutine: 100000
        coroutine_hook_flags: ~
        max_connection: 10000
        buffer_output_size: 33554432
        socket_buffer_size: 134217728
        package_max_length: 10485760
        enable_compression: true
        compression_level: 3
        compression_min_length: 20
        websocket_compression: true
        daemonize: false
        heartbeat_interval: 30
        heartbeat_idle_time: 60
        enable_reuse_port: false
        thread_mode: false
        base_mode: false

    # Database Pools
    database:
        enable_pool: true
        mysql:
            pool_size: 10
            timeout: 5.0
        postgresql:
            pool_size: 10
            timeout: 5.0
        redis:
            pool_size: 20
            timeout: 3.0

    # Task Workers
    task:
        worker_num: 4
        max_request: 10000

    # Scheduler
    scheduler:
        enabled: true

    # Rate Limiter
    rate_limiter:
        enabled: true
        max_requests: 100
        window_seconds: 60

    # Metrics
    metrics:
        enabled: true
        export_interval: 60

    # Thread Pool (Swoole 6.1)
    thread_pool:
        enabled: false
        size: 4

    # Process Pool
    process_pool:
        enabled: false
        size: 4

    # WebSocket
    websocket:
        enabled: false
        max_frame_size: 65535
        compression: true

    # Debug
    debug:
        enabled: false
        enable_dd: true
        enable_var_dump: true
```

---

# Français

Ce document détaille toutes les fonctionnalités Swoole 6.1.4 exploitées dans ce bundle pour maximiser les performances de Symfony 7/8.

## 📊 Vue d'ensemble

| Fonctionnalité | Statut | Gain de Performance |
|----------------|--------|---------------------|
| **MySQL Connection Pool** | ✅ | 10-100x |
| **PostgreSQL Connection Pool** | ✅ | 10-100x |
| **Redis Connection Pool** | ✅ | 5-20x |
| **Task Workers** | ✅ | 100-1000x |
| **Scheduler/Timer** | ✅ | ∞ (async) |
| **Lock/Mutex** | ✅ | Thread-safe |
| **Opérations Atomiques** | ✅ | 1000x+ |
| **Système de Queue** | ✅ | 100-1000x |
| **Rate Limiter** | ✅ | Protection |
| **Collecteur de Métriques** | ✅ | Monitoring |
| **Cache Swoole Table** | ✅ | 1000-10000x |
| **Sessions Swoole Table** | ✅ | 1000-10000x |
| **Client HTTP/2** | ✅ | Multiplexage |
| **Coroutines Avancées** | ✅ | Non-bloquant |
| **FileSystem Async** | ✅ | Non-bloquant |
| **Thread Pool (6.1)** | ✅ | CPU-intensive |
| **Process Manager** | ✅ | Workers parallèles |
| **Socket Async** | ✅ | Non-bloquant |
| **DNS Async** | ✅ | Non-bloquant |

---

## 1. Pools de Connexions Base de Données

### Pool MySQL

Connexions MySQL haute performance utilisant Swoole Coroutine MySQL.

```php
use Toadbeatz\SwooleBundle\Database\ConnectionPool;

// Créer le pool
$pool = new ConnectionPool([
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'password' => 'password',
    'database' => 'myapp',
    'charset' => 'utf8mb4',
], poolSize: 10, timeout: 5.0);

// Obtenir une connexion et exécuter une requête
$connection = $pool->get();
try {
    $result = $connection->query('SELECT * FROM users WHERE id = 1');
    // Traiter le résultat...
} finally {
    $pool->put($connection); // Retourner au pool
}

// Obtenir les statistiques
$stats = $pool->getStats();
// ['size' => 10, 'current' => 5, 'available' => 3, 'in_use' => 2]
```

### Pool PostgreSQL

Connexions PostgreSQL haute performance utilisant Swoole Coroutine PostgreSQL.

```php
use Toadbeatz\SwooleBundle\Database\PostgreSQLPool;

$pool = new PostgreSQLPool([
    'host' => '127.0.0.1',
    'port' => 5432,
    'database' => 'myapp',
    'user' => 'postgres',
    'password' => 'password',
], poolSize: 10, timeout: 5.0);

// Requête simple avec paramètres
$users = $pool->query('SELECT * FROM users WHERE active = $1', [true]);

// Exécuter une instruction (INSERT, UPDATE, DELETE)
$affected = $pool->execute(
    'INSERT INTO users (name, email) VALUES ($1, $2)',
    ['John', 'john@example.com']
);

// Transaction
$conn = $pool->beginTransaction();
try {
    $conn->query('INSERT INTO orders ...');
    $conn->query('UPDATE inventory ...');
    $pool->commit($conn);
} catch (\Exception $e) {
    $pool->rollback($conn);
    throw $e;
}
```

### Pool Redis

Connexions Redis haute performance utilisant Swoole Coroutine Redis.

```php
use Toadbeatz\SwooleBundle\Database\RedisPool;

$pool = new RedisPool([
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => 'secret',
    'database' => 0,
], poolSize: 20, timeout: 3.0);

// Opérations de base
$pool->set('user:1:name', 'John', ttl: 3600);
$name = $pool->get_value('user:1:name');
$pool->delete('user:1:name');

// Vérifier l'existence
if ($pool->exists('user:1:name')) {
    // ...
}

// Compteurs
$pool->incr('page:views');
$pool->decr('stock:item:1');

// Opérations Hash
$pool->hset('user:1', 'name', 'John');
$pool->hset('user:1', 'email', 'john@example.com');
$name = $pool->hget('user:1', 'name');
$user = $pool->hgetall('user:1');

// Opérations List
$pool->lpush('queue:emails', 'email1', 'email2');
$email = $pool->rpop('queue:emails');

// Expiration
$pool->expire('session:abc', 1800);
$ttl = $pool->ttl('session:abc');

// Pipeline (opérations par lot)
$results = $pool->pipeline(function ($pipe) {
    $pipe->set('key1', 'value1');
    $pipe->set('key2', 'value2');
    $pipe->get('key1');
});
```

---

## 2. Coroutines Avancées (Swoole 6.1)

### Exécution Parallèle

Exécuter plusieurs opérations de manière concurrente.

```php
use Toadbeatz\SwooleBundle\Coroutine\CoroutineHelper;

// Exécuter en parallèle
$results = CoroutineHelper::parallel([
    fn() => $userService->fetchUser($id),
    fn() => $productService->fetchProducts(),
    fn() => $orderService->fetchOrders($userId),
]);
// $results = [user, products, orders]

// Parallèle avec WaitGroup (meilleure synchronisation)
$results = CoroutineHelper::parallelWait([
    'user' => fn() => $userService->fetchUser($id),
    'products' => fn() => $productService->fetchProducts(),
]);
// $results = ['user' => ..., 'products' => ...]
```

### Race & Timeout

```php
// Race - Le premier résultat réussi gagne
$result = CoroutineHelper::race([
    fn() => $this->fetchFromPrimaryServer(),
    fn() => $this->fetchFromBackupServer(),
], timeout: 5.0);

// Wrapper avec timeout
$result = CoroutineHelper::withTimeout(
    fn() => $this->longRunningOperation(),
    timeout: 10.0
);
// Lance RuntimeException si timeout dépassé
```

### Retry & Circuit Breaker

```php
// Retry avec backoff exponentiel
$result = CoroutineHelper::retry(
    fn() => $this->unreliableApiCall(),
    maxAttempts: 5,        // Tentatives max
    initialDelay: 0.1,     // Délai initial (secondes)
    maxDelay: 5.0,         // Délai max entre les retries
    multiplier: 2.0        // Multiplicateur exponentiel
);

// Pattern Circuit Breaker
$result = CoroutineHelper::withCircuitBreaker(
    fn() => $this->externalService->call(),
    name: 'payment_gateway',    // Nom du circuit
    failureThreshold: 5,        // Échecs avant ouverture
    recoveryTimeout: 30.0       // Secondes avant demi-ouverture
);
// Lance RuntimeException si le circuit est ouvert
```

### Gestion des Coroutines

```php
// Créer une coroutine
$cid = CoroutineHelper::create(fn() => doSomething());

// Annuler une coroutine (fonctionnalité Swoole 6.1)
CoroutineHelper::cancel($cid, throwException: true);

// Vérifier si annulée
if (CoroutineHelper::isCancelled()) {
    // Nettoyage et sortie
}

// Céder la coroutine actuelle
CoroutineHelper::yield();

// Reprendre une coroutine cédée
CoroutineHelper::resume($cid);

// Différer l'exécution (s'exécute à la sortie de la coroutine)
CoroutineHelper::defer(fn() => $this->cleanup());

// Obtenir l'ID de la coroutine actuelle
$currentCid = CoroutineHelper::getCid();

// Obtenir l'ID de la coroutine parente
$parentCid = CoroutineHelper::getPcid();

// Vérifier si dans un contexte de coroutine
if (CoroutineHelper::inCoroutine()) {
    // Dans une coroutine
}

// Obtenir les statistiques
$stats = CoroutineHelper::getStats();
// ['coroutine_num' => 42, 'coroutine_peak_num' => 100, ...]

// Obtenir tous les IDs de coroutines
$cids = CoroutineHelper::listCoroutines();
```

---

## 3. Client HTTP/2

Client HTTP/2 haute performance avec support du multiplexage.

```php
use Toadbeatz\SwooleBundle\Http\Http2Client;

$client = new Http2Client('api.example.com', 443, ssl: true, timeout: 5.0);

// Définir des paramètres personnalisés
$client->setSettings([
    'timeout' => 10.0,
    'ssl_verify_peer' => false,
]);

// Connexion
$client->connect();

// Requêtes simples
$response = $client->get('/users');
$response = $client->post('/users', '{"name":"John"}', ['content-type' => 'application/json']);
$response = $client->postJson('/users', ['name' => 'John']);
$response = $client->put('/users/1', '{"name":"Jane"}');
$response = $client->delete('/users/1');

// Multiplexage - Envoyer plusieurs requêtes en parallèle
$responses = $client->sendMultiple([
    ['method' => 'GET', 'path' => '/users'],
    ['method' => 'GET', 'path' => '/products'],
    ['method' => 'POST', 'path' => '/orders', 'body' => '{"item": 1}', 'headers' => ['content-type' => 'application/json']],
]);

// Utiliser la réponse
if ($response->isOk()) {
    $statusCode = $response->getStatusCode();
    $headers = $response->getHeaders();
    $body = $response->getBody();
    $data = $response->toArray(); // Décodage JSON
}

// Vérifier le type de réponse
$response->isRedirect();    // 3xx
$response->isClientError(); // 4xx
$response->isServerError(); // 5xx

// Fermer la connexion
$client->close();
```

---

## 4. FileSystem Async

Opérations de fichiers non-bloquantes utilisant les coroutines Swoole.

```php
use Toadbeatz\SwooleBundle\FileSystem\AsyncFileSystem;

// Lecture/Écriture de fichiers
$content = AsyncFileSystem::readFile('/path/to/file.txt');
AsyncFileSystem::writeFile('/path/to/output.txt', $content);
AsyncFileSystem::appendFile('/path/to/log.txt', "Entrée de log\n");

// Opérations JSON
$config = AsyncFileSystem::readJson('/path/to/config.json');
AsyncFileSystem::writeJson('/path/to/output.json', $data, JSON_PRETTY_PRINT);

// Opérations sur fichiers
AsyncFileSystem::copy('/source.txt', '/dest.txt');
AsyncFileSystem::rename('/old.txt', '/new.txt');
AsyncFileSystem::unlink('/file.txt');

// Opérations sur répertoires
AsyncFileSystem::mkdir('/path/to/dir', 0755, recursive: true);
AsyncFileSystem::rmdir('/path/to/dir', recursive: true);

// Informations sur fichiers
$exists = AsyncFileSystem::exists('/path/to/file.txt');
$stat = AsyncFileSystem::stat('/path/to/file.txt');
$size = AsyncFileSystem::fileSize('/path/to/file.txt');

// Lire fichier ligne par ligne (générateur)
foreach (AsyncFileSystem::readLines('/path/to/file.txt') as $line) {
    // Traiter la ligne
}

// Exécuter une commande shell
$result = AsyncFileSystem::exec('ls -la /path');
// ['code' => 0, 'output' => '...', 'signal' => 0]

// Attendre une modification de fichier (comme inotify)
$changed = AsyncFileSystem::waitFileChange('/path/to/file.txt', timeout: 30.0);
```

---

## 13. Commandes de Gestion du Serveur

### Démarrer le Serveur

Démarrer le serveur HTTP Swoole :

```bash
# Mode production
php bin/console swoole:server:start

# Avec host/port personnalisés
php bin/console swoole:server:start --host=127.0.0.1 --port=8080
```

### Arrêter le Serveur

Arrêter le serveur en cours d'exécution :

```bash
php bin/console swoole:server:stop

# Forcer l'arrêt
php bin/console swoole:server:stop --force
```

### Recharger le Serveur (Zero-Downtime)

**Commande de rechargement prête pour la production** - Rechargez les workers de manière gracieuse pour appliquer les changements de code sans arrêter le serveur :

```bash
# Rechargement standard (vide le cache + recharge les workers)
php bin/console swoole:server:reload

# Ignorer le vidage du cache
php bin/console swoole:server:reload --no-cache-clear

# Vider uniquement le cache sans recharger les workers
php bin/console swoole:server:reload --only-cache

# Forcer le vidage de l'OPcache
php bin/console swoole:server:reload --opcache
```

**Comment ça fonctionne :**
1. Vide le cache Symfony (`var/cache`)
2. Vide l'OPcache pour garantir le chargement du nouveau code
3. Envoie le signal de rechargement (SIGUSR1) à tous les workers
4. Les workers terminent les requêtes en cours gracieusement
5. Les workers se rechargent avec le nouveau code (zero downtime)

**Parfait pour les déploiements en production !** Après avoir déployé du nouveau code, exécutez simplement la commande de rechargement pour appliquer les changements sans interruption de service.

**Exemple de Workflow en Production :**

```bash
# 1. Déployer votre code
git pull origin main
composer install --no-dev --optimize-autoloader

# 2. Recharger les workers (zero downtime !)
php bin/console swoole:server:reload

# Terminé ! Le nouveau code est actif sans aucune interruption 🎉
```

### Hot Reload (Développement)

Démarrer le serveur avec surveillance automatique des fichiers et rechargement :

```bash
php bin/console swoole:server:watch

# Intervalle de vérification personnalisé
php bin/console swoole:server:watch --poll-interval=500

# Ne pas vider le cache lors du rechargement
php bin/console swoole:server:watch --no-clear-cache
```

La fonctionnalité hot-reload surveille automatiquement les changements de fichiers dans les répertoires `src/`, `config/`, et `templates/` et recharge les workers lorsque des changements sont détectés.

---

## Référence de Configuration Complète

```yaml
swoole:
    # Serveur HTTP
    http:
        host: '0.0.0.0'
        port: 9501
        options:
            open_http2_protocol: true
            open_websocket_protocol: false
            enable_static_handler: true
            document_root: '%kernel.project_dir%/public'
            static_handler_locations:
                - /assets
                - /bundles

    # HTTPS/SSL
    https:
        enabled: false
        port: 9502
        cert: ~
        key: ~
        ca: ~
        verify_peer: false
        protocols: ~  # TLSv1.2 | TLSv1.3

    # Paramètres HTTP/2
    http2:
        header_table_size: 4096
        initial_window_size: 65535
        max_concurrent_streams: 128
        max_frame_size: 16384
        max_header_list_size: 4096

    # Hot Reload
    hot_reload:
        enabled: true
        watch:
            - src
            - config
            - templates
        interval: 500

    # Performance
    performance:
        worker_num: ~              # Auto-détection du nombre de CPU
        max_request: 10000
        enable_coroutine: true
        max_coroutine: 100000
        coroutine_hook_flags: ~
        max_connection: 10000
        buffer_output_size: 33554432
        socket_buffer_size: 134217728
        package_max_length: 10485760
        enable_compression: true
        compression_level: 3
        compression_min_length: 20
        websocket_compression: true
        daemonize: false
        heartbeat_interval: 30
        heartbeat_idle_time: 60
        enable_reuse_port: false
        thread_mode: false
        base_mode: false

    # Pools Base de Données
    database:
        enable_pool: true
        mysql:
            pool_size: 10
            timeout: 5.0
        postgresql:
            pool_size: 10
            timeout: 5.0
        redis:
            pool_size: 20
            timeout: 3.0

    # Task Workers
    task:
        worker_num: 4
        max_request: 10000

    # Scheduler
    scheduler:
        enabled: true

    # Rate Limiter
    rate_limiter:
        enabled: true
        max_requests: 100
        window_seconds: 60

    # Métriques
    metrics:
        enabled: true
        export_interval: 60

    # Thread Pool (Swoole 6.1)
    thread_pool:
        enabled: false
        size: 4

    # Process Pool
    process_pool:
        enabled: false
        size: 4

    # WebSocket
    websocket:
        enabled: false
        max_frame_size: 65535
        compression: true

    # Debug
    debug:
        enabled: false
        enable_dd: true
        enable_var_dump: true
```

---

**Compatibilité / Compatibility**: Symfony 7.0, 7.1, 7.2, 8.0 | PHP 8.2, 8.3, 8.4 | Swoole 6.0+
