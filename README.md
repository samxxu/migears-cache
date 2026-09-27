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
- Built-in `ArrayCache` for unit testing, development environments, and in-request caching
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

An in-memory `CacheInterface` implementation for when no persistent store is needed:
- **Unit tests** — no Redis/ext-redis required
- **Development environments** — stand-in where Redis is unavailable
- **In-request caching** — store computed values for the lifetime of one request

Values live in a plain PHP array and are **lost when the request ends**; use `RedisCache` when data must be shared across requests or processes.

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
$rest->set(Redis::class, function () {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
});
// in a resource:
$cache = new RedisCache($this->resolve(Redis::class));
```

Values are stored so that a string is never read back as something else. A string is written verbatim, unless it begins with an internal marker — then it is stored as a payload too, because the reader has no other way to tell the two apart. Only marked values are ever unserialized, so a string shaped like `O:8:"stdClass":0:{}` stays a string.

`$allowedClasses` bounds what a marked payload may instantiate on read. The default (`true`) keeps PSR-16 object support; pass `false`, or the classes you actually cache, if entries can be written by anyone outside your application:

```php
$cache = new RedisCache($redis, allowedClasses: false);
```

With `false`, a payload carrying an object comes back as `__PHP_Incomplete_Class` instead of the object; arrays and scalars are unaffected.

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

A counter keeps the shape it was found in: one written by `set()` stays an int, so `get()` reads it back as an int, while a bare integer another client left behind stays a string. Both are adjusted inside one Redis script, so the read and the write cannot interleave, and the TTL survives.

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

### Cache Key Contract

PSR-16 reserves certain characters (`{}()/\@:`) and requires implementations to reject keys containing them with `InvalidArgumentException`. Both implementations validate every key and throw `Psr\SimpleCache\InvalidArgumentException` for a reserved character or a non-string key, so the array and Redis backends agree on what a key is. Keys may otherwise be any string; empty keys are accepted.

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
- 内置 `ArrayCache` 用于单元测试、开发环境与单请求内缓存
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

`CacheInterface` 的内存实现，适用于不需要持久化存储的场景：
- **单元测试** — 无需 Redis / ext-redis 即可运行
- **开发环境** — 在无 Redis 的环境下作为临时替身
- **单请求内缓存** — 在一次请求生命周期内保存已计算的结果

数据存放在普通 PHP 数组中，**请求结束即丢失**；需要跨请求或跨进程共享数据时，请使用 `RedisCache`。

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
$rest->set(Redis::class, function () {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
});
// 在资源类中：
$cache = new RedisCache($this->resolve(Redis::class));
```

存储方式是「无歧义」的：字符串原样写入，除非它以内部标记开头——那时它也会被当作载荷存储，因为读取方没有别的办法区分两者。只有带标记的值会被反序列化，因此形如 `O:8:"stdClass":0:{}` 的字符串读出来仍然是字符串。

`$allowedClasses` 限定读回时载荷可以实例化哪些类。默认值（`true`）保留 PSR-16 的对象支持；若缓存条目可能由应用之外的人写入，可传 `false`，或只列出你确实会缓存的类：

```php
$cache = new RedisCache($redis, allowedClasses: false);
```

传 `false` 时，带对象的载荷会以 `__PHP_Incomplete_Class` 返回而非该对象；数组与标量不受影响。

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

计数器的存储形状保持不变：由 `set()` 写入的计数读回来是 int，而其他客户端留下的裸整数仍读作字符串。两者都在同一段 Redis 脚本中调整，因此读写不会被插入，TTL 也会保留。

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

### 缓存键约定

PSR-16 保留了一些字符（`{}()/\@:`）并要求实现对其抛 `InvalidArgumentException`。两个实现都会校验每一个键，遇到保留字符或非字符串键时抛出 `Psr\SimpleCache\InvalidArgumentException`，从而使数组后端与 Redis 后端对「什么是合法的键」保持一致。除此之外键可以是任意字符串；空键允许使用。

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
