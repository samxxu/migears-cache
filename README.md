# migears/cache

![Version](https://img.shields.io/badge/version-2.0.0-blue)

A lightweight PHP cache abstraction layer that provides a clean, unified API with in-memory array cache and Redis implementations.

> **Background**: miGears is the open-source successor of **TinyGears**, a
> self-developed PHP framework. It was renamed and open-sourced recently because
> the name *TinyGears* is already taken in the open-source community.

## Features

- **PSR-16 compliant** — fully compatible with `Psr\SimpleCache\CacheInterface`
- PHP 8.1+, using modern syntax features (type declarations, constructor property promotion, readonly, match expressions, etc.)
- Follows PSR-4 autoloading standard, namespace `MiGears\Cache`
- Minimalist API, ready to use after `new`
- Built-in `ArrayCache` for unit testing and development environments
- `RedisCache` for PSR-16 caching; queue operations and distributed locks live in the separate `migears/data-structure` package
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

Passing an already connected Redis instance. `RedisCache` never connects on its own; establishing the connection belongs to the caller.

```php
use MiGears\Cache\RedisCache;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$cache = new RedisCache($redis);
```

When using miGears in a web environment, inject the connection in `MiRest` and obtain it via the service registry:

```php
$rest->set(Redis::class, fn () => (new Redis())->connect('127.0.0.1', 6379));
// in a resource:
$cache = new RedisCache($this->service(Redis::class));
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

### Redis Data Structures & Distributed Locks

Queue operations and distributed locks are not part of `RedisCache`; they live in the separate `migears/data-structure` package:

- **Queue** — `RedisDataStructure::listPush()` / `listPop()`
- **Distributed lock** — `RedisLock::lock()` / `unlock()`

```bash
composer require migears/data-structure
```

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
- `RedisCache` 提供 PSR-16 缓存；队列操作与分布式锁在独立的 `migears/data-structure` 包中
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

传入已连接的 Redis 实例。`RedisCache` 自身不会去连接，建立连接由调用方负责。

```php
use MiGears\Cache\RedisCache;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$cache = new RedisCache($redis);
```

在 miGears 的 web 环境中，通过 `MiRest` 注入连接，再经服务注册中心取得：

```php
$rest->set(Redis::class, fn () => (new Redis())->connect('127.0.0.1', 6379));
// 在资源类中：
$cache = new RedisCache($this->service(Redis::class));
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

### Redis 数据结构与分布式锁

队列操作与分布式锁不属于 `RedisCache`，它们位于独立的 `migears/data-structure` 包中：

- **队列** — `RedisDataStructure::listPush()` / `listPop()`
- **分布式锁** — `RedisLock::lock()` / `unlock()`

```bash
composer require migears/data-structure
```

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
