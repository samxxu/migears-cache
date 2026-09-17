# migears/cache

![Version](https://img.shields.io/badge/version-2.0.0-blue)

A lightweight PHP cache abstraction layer that provides a clean, unified API with in-memory array cache and Redis implementations.

## Features

- **PSR-16 compliant** — fully compatible with `Psr\SimpleCache\CacheInterface`
- PHP 8.1+, using modern syntax features (type declarations, constructor property promotion, readonly, match expressions, etc.)
- Follows PSR-4 autoloading standard, namespace `MiGears\Cache`
- Minimalist API, ready to use after `new`
- Built-in `ArrayCache` for unit testing and development environments
- `RedisCache` with queue operations and distributed locks
- **`getOrSet()`** — compute and cache on miss in one call
- **`withPrefix()`** — key namespacing for shared cache backends
- Supports `int`, `DateInterval`, or `null` TTL formats
- Optional PSR-3 logger injection

## Installation

```bash
composer require migears/cache
```

> Using RedisCache requires the `redis` extension: `pecl install redis`

## Quick Start

### ArrayCache (In-Memory Cache)

```php
use MiGears\Cache\ArrayCache;

$cache = new ArrayCache();

$cache->set('key', 'value');
$value = $cache->get('key');       // 'value'
$cache->has('key');                // true
$cache->delete('key');
$cache->clear();
```

### RedisCache

Using connection parameters:

```php
use MiGears\Cache\RedisCache;

$cache = new RedisCache([
    'host'       => '127.0.0.1',
    'port'       => 6379,
    'auth'       => 'password',    // optional
    'dbindex'    => 0,             // optional
    'persistent' => false,         // optional, whether to use persistent connection
    'timeout'    => 0.0,           // optional, timeout duration
]);
```

Passing an already connected Redis instance:

```php
use MiGears\Cache\RedisCache;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$cache = new RedisCache($redis);
```

## API

### Basic Methods

| Method | Description |
|--------|-------------|
| `get(string $key, mixed $default = null): mixed` | Get cache value |
| `set(string $key, mixed $value, null\|int\|DateInterval $ttl = null): bool` | Set cache value |
| `delete(string $key): bool` | Delete cache item |
| `has(string $key): bool` | Check if cache exists |
| `clear(): bool` | Clear all cache |
| `getMultiple(iterable $keys, mixed $default = null): array` | Batch get |
| `setMultiple(iterable $values, null\|int\|DateInterval $ttl = null): bool` | Batch set |
| `deleteMultiple(iterable $keys): bool` | Batch delete |
| `incr(string $key, int $step = 1): int` | Increment atomically |
| `decr(string $key, int $step = 1): int` | Decrement atomically |
| `getOrSet(string $key, callable $factory, $ttl = null): mixed` | Get or compute & store |
| `withPrefix(string $prefix): static` | Return namespaced instance |

### getOrSet — Lazy cache pattern

Compute and store a value only on cache miss:

```php
$value = $cache->getOrSet('user_profile_42', function () use ($userId) {
    return $this->db->fetchUser($userId);  // expensive operation
}, 3600); // TTL optional
```

### Key Prefix — Namespacing

Isolate cache keys when sharing a backend across apps or modules:

```php
$appCache = $cache->withPrefix('myapp:');

$appCache->set('user', 'alice');   // stores as "myapp:user"
$appCache->get('user');            // returns "alice"
$appCache->clear();                // only clears keys starting with "myapp:"
```

> ⚠️ **Redis `clear()` warning**: When no prefix is set, `clear()` calls `FLUSHDB` which deletes **all** keys in the current Redis database. Use `withPrefix()` on shared Redis instances to avoid accidental data loss.

### RedisCache Advanced Methods

| Method | Description |
|--------|-------------|
| `push(string $key, mixed ...$values): int` | Push to the right side of a queue |
| `pop(string $key): mixed` | Pop from the left side of a queue |
| `lock(string $key, int $ttl): bool` | Distributed lock (SET NX EX) |

## Logging

Supports injection of any PSR-3 compatible logging implementation:

```php
use MiGears\Cache\ArrayCache;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('cache');
$logger->pushHandler(new StreamHandler('cache.log'));

$cache = new ArrayCache($logger);
```

## Testing

```bash
# Unit tests (ArrayCache)
composer install
./vendor/bin/phpunit

# Integration tests (requires Redis service)
./vendor/bin/phpunit --group integration
```

Redis connection can be configured via environment variables:
- `REDIS_HOST` (default `127.0.0.1`)
- `REDIS_PORT` (default `6379`)
- `REDIS_DB` (default `15`)

## License

MIT

---

# migears/cache

![Version](https://img.shields.io/badge/version-2.0.0-blue)

轻量级 PHP 缓存抽象层，提供简洁统一的 API，支持内存数组缓存和 Redis 实现。

## 特性

- **PSR-16 兼容** — 完全兼容 `Psr\SimpleCache\CacheInterface`
- PHP 8.1+，使用现代语法特性（类型声明、构造器属性提升、readonly、match 表达式等）
- 遵循 PSR-4 自动加载规范，命名空间 `MiGears\Cache`
- 极简 API，`new` 了就能用
- 内置 `ArrayCache` 用于单元测试和开发环境
- `RedisCache` 支持队列操作和分布式锁
- **`getOrSet()`** — 一次调用完成"读缓存-计算-写缓存"
- **`withPrefix()`** — 共享缓存后端的键命名空间隔离
- 支持 `int`、`DateInterval`、`null` 三种 TTL 格式
- 可选 PSR-3 日志注入

## 安装

```bash
composer require migears/cache
```

> 使用 RedisCache 需要安装 `redis` 扩展：`pecl install redis`

## 快速开始

### ArrayCache（内存缓存）

```php
use MiGears\Cache\ArrayCache;

$cache = new ArrayCache();

$cache->set('key', 'value');
$value = $cache->get('key');       // 'value'
$cache->has('key');                // true
$cache->delete('key');
$cache->clear();
```

### RedisCache

使用连接参数：

```php
use MiGears\Cache\RedisCache;

$cache = new RedisCache([
    'host'       => '127.0.0.1',
    'port'       => 6379,
    'auth'       => 'password',    // 可选
    'dbindex'    => 0,             // 可选
    'persistent' => false,         // 可选，是否长连接
    'timeout'    => 0.0,           // 可选，超时时间
]);
```

传入已连接的 Redis 实例：

```php
use MiGears\Cache\RedisCache;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$cache = new RedisCache($redis);
```

## API

### 基础方法

| 方法 | 说明 |
|------|------|
| `get(string $key, mixed $default = null): mixed` | 获取缓存值 |
| `set(string $key, mixed $value, null\|int\|DateInterval $ttl = null): bool` | 设置缓存值 |
| `delete(string $key): bool` | 删除缓存项 |
| `has(string $key): bool` | 检查缓存是否存在 |
| `clear(): bool` | 清空所有缓存 |
| `getMultiple(iterable $keys, mixed $default = null): array` | 批量获取 |
| `setMultiple(iterable $values, null\|int\|DateInterval $ttl = null): bool` | 批量设置 |
| `deleteMultiple(iterable $keys): bool` | 批量删除 |
| `incr(string $key, int $step = 1): int` | 原子自增 |
| `decr(string $key, int $step = 1): int` | 原子自减 |
| `getOrSet(string $key, callable $factory, $ttl = null): mixed` | 获取或计算并存储 |
| `withPrefix(string $prefix): static` | 返回带前缀的实例 |

### getOrSet — 懒缓存模式

只在缓存未命中时计算并存储值：

```php
$value = $cache->getOrSet('user_profile_42', function () use ($userId) {
    return $this->db->fetchUser($userId);  // 耗时操作
}, 3600); // TTL 可选
```

### Key Prefix — 键命名空间

在共享后端上隔离不同应用/模块的缓存键：

```php
$appCache = $cache->withPrefix('myapp:');

$appCache->set('user', 'alice');   // 实际存储为 "myapp:user"
$appCache->get('user');            // 返回 "alice"
$appCache->clear();                // 只清除以 "myapp:" 开头的键
```

> ⚠️ **Redis `clear()` 注意**：未设置前缀时，`clear()` 会调用 `FLUSHDB` 删除当前 Redis 数据库中**所有**键。在共享 Redis 实例上请使用 `withPrefix()` 避免误删数据。

### RedisCache 高级方法

| 方法 | 说明 |
|------|------|
| `push(string $key, mixed ...$values): int` | 队列右侧入队 |
| `pop(string $key): mixed` | 队列左侧出队 |
| `lock(string $key, int $ttl): bool` | 分布式锁（SET NX EX） |

## 日志

支持注入任意 PSR-3 兼容的日志实现：

```php
use MiGears\Cache\ArrayCache;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('cache');
$logger->pushHandler(new StreamHandler('cache.log'));

$cache = new ArrayCache($logger);
```

## 测试

```bash
# 单元测试（ArrayCache）
composer install
./vendor/bin/phpunit

# 集成测试（需要 Redis 服务）
./vendor/bin/phpunit --group integration
```

Redis 连接可通过环境变量配置：
- `REDIS_HOST`（默认 `127.0.0.1`）
- `REDIS_PORT`（默认 `6379`）
- `REDIS_DB`（默认 `15`）

## License

MIT
