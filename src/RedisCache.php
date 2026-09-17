<?php

declare(strict_types=1);

namespace MiGears\Cache;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Redis;
use MiGears\Cache\Exception\CacheException;

/**
 * Redis cache implementation.
 *
 * Usage:
 *   new RedisCache($redis);          // pass an already connected Redis instance
 *   new RedisCache(['host' => '...']); // connection parameters
 *
 * Features:
 *   - Full PSR-16 compatibility
 *   - Atomic increment / decrement
 *   - Queue operations (push / pop)
 *   - Distributed lock (SET NX EX)
 *   - Key prefix support via withPrefix()
 */
class RedisCache implements CacheInterface
{
    public const VERSION = '2.0.0';

    private readonly Redis $redis;
    private readonly LoggerInterface $logger;
    private string $prefix = '';

    public function __construct(
        Redis|array $redis,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->redis = $redis instanceof Redis ? $redis : $this->connect($redis);
    }

    /** @param array<string, mixed> $config */
    private function connect(array $config): Redis
    {
        try {
            $redis = new Redis();
            $host = (string) ($config['host'] ?? '127.0.0.1');
            $port = (int) ($config['port'] ?? 6379);
            $timeout = (float) ($config['timeout'] ?? 0.0);
            $persistent = (bool) ($config['persistent'] ?? false);
            $persistent ? $redis->pconnect($host, $port, $timeout) : $redis->connect($host, $port, $timeout);
            isset($config['auth']) && $redis->auth($config['auth']);
            isset($config['dbindex']) && $redis->select((int) $config['dbindex']);
            return $redis;
        } catch (\Throwable $e) {
            $this->logger->error('Redis connection failed', ['exception' => $e]);
            throw new CacheException('Redis connection failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $pkey = $this->prefix . $key;
            $value = $this->redis->get($pkey);
            return $value === false ? $default : $this->unserialize($value);
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache get error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        try {
            $pkey = $this->prefix . $key;
            $serialized = $this->serialize($value);
            $ttlSeconds = $this->ttlToSeconds($ttl);
            return $ttlSeconds === null
                ? (bool) $this->redis->set($pkey, $serialized)
                : (bool) $this->redis->setex($pkey, $ttlSeconds, $serialized);
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache set error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function delete(string $key): bool
    {
        try {
            $pkey = $this->prefix . $key;
            return $this->redis->del($pkey) >= 0;
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache delete error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function has(string $key): bool
    {
        try {
            $pkey = $this->prefix . $key;
            return (bool) $this->redis->exists($pkey);
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache has error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Clears cache entries.
     *
     * WARNING: When no prefix is set, this calls FLUSHDB which clears ALL keys
     * in the current Redis database. Use with caution on shared instances.
     * When a prefix is set via withPrefix(), only prefixed keys are cleared.
     */
    public function clear(): bool
    {
        try {
            if ($this->prefix === '') {
                return $this->redis->flushDB();
            }
            // Only clear keys matching our prefix
            $keys = $this->redis->keys($this->prefix . '*');
            if ($keys === [] || $keys === false) {
                return true;
            }
            $this->redis->del(...$keys);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache clear error', ['exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getMultiple(iterable $keys, mixed $default = null): array
    {
        try {
            $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);
            $prefixedKeys = array_map(fn($k) => $this->prefix . $k, $keyArray);
            $values = $this->redis->mget($prefixedKeys);
            $result = [];
            foreach ($keyArray as $i => $key) {
                $result[$key] = $values[$i] === false ? $default : $this->unserialize($values[$i]);
            }
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache getMultiple error', ['exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        try {
            $ttlSeconds = $this->ttlToSeconds($ttl);
            $serialized = [];
            foreach ($values as $key => $value) {
                $serialized[$this->prefix . $key] = $this->serialize($value);
            }
            if ($ttlSeconds === null) {
                return $this->redis->mset($serialized);
            }
            $pipe = $this->redis->multi(Redis::PIPELINE);
            foreach ($serialized as $pkey => $value) {
                $pipe->setex($pkey, $ttlSeconds, $value);
            }
            $pipe->exec();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache setMultiple error', ['exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function deleteMultiple(iterable $keys): bool
    {
        try {
            $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);
            $prefixedKeys = array_map(fn($k) => $this->prefix . $k, $keyArray);
            $this->redis->del(...$prefixedKeys);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache deleteMultiple error', ['exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getOrSet(string $key, callable $factory, null|int|\DateInterval $ttl = null): mixed
    {
        $value = $this->get($key);
        if ($value !== null || $this->has($key)) {
            return $value;
        }
        $computed = $factory();
        $this->set($key, $computed, $ttl);
        return $computed;
    }

    public function withPrefix(string $prefix): static
    {
        $copy = new self($this->redis, $this->logger);
        $copy->prefix = $prefix;
        return $copy;
    }

    public function incr(string $key, int $step = 1): int
    {
        try {
            $pkey = $this->prefix . $key;
            return match ($step) {
                1 => $this->redis->incr($pkey),
                default => $this->redis->incrBy($pkey, $step),
            };
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache incr error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function decr(string $key, int $step = 1): int
    {
        try {
            $pkey = $this->prefix . $key;
            return match ($step) {
                1 => $this->redis->decr($pkey),
                default => $this->redis->decrBy($pkey, $step),
            };
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache decr error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    // --- Redis-specific advanced methods ---

    /** Queue: enqueue from the right */
    public function push(string $key, mixed ...$values): int
    {
        try {
            $pkey = $this->prefix . $key;
            $serialized = array_map(fn($v) => $this->serialize($v), $values);
            return $this->redis->rPush($pkey, ...$serialized);
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache push error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** Queue: dequeue from the left */
    public function pop(string $key): mixed
    {
        try {
            $pkey = $this->prefix . $key;
            $value = $this->redis->lPop($pkey);
            return $value === false ? null : $this->unserialize($value);
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache pop error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** Distributed lock (SET NX EX) */
    public function lock(string $key, int $ttl): bool
    {
        try {
            $pkey = $this->prefix . $key;
            return (bool) $this->redis->set($pkey, '1', ['nx', 'ex' => $ttl]);
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache lock error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    // --- Internal ---

    private function serialize(mixed $value): string
    {
        return is_string($value) ? $value : serialize($value);
    }

    private function unserialize(string $value): mixed
    {
        $unserialized = @unserialize($value);
        return $unserialized !== false || $value === serialize(false) ? $unserialized : $value;
    }

    private function ttlToSeconds(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if ($ttl instanceof \DateInterval) {
            return (int) $ttl->format('%a') * 86400
                + (int) $ttl->format('%h') * 3600
                + (int) $ttl->format('%i') * 60
                + (int) $ttl->format('%s');
        }
        return $ttl;
    }
}
