<?php

declare(strict_types=1);

namespace MiGears\Cache;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use MiGears\Cache\Exception\CacheException;

/**
 * Redis cache implementation.
 *
 * This class never connects to Redis on its own. It only wraps an already
 * connected Redis instance; establishing the connection belongs to the caller.
 *
 * Usage (web / miGears): inject the connection in MiRest, then obtain it via
 * the service registry. The factory must return the connected instance, and
 * connect() returns a bool, so it cannot be the return value itself:
 *   $rest->set(Redis::class, function () {
 *       $redis = new Redis();
 *       $redis->connect('127.0.0.1', 6379);
 *       return $redis;
 *   });
 *   // in a resource:
 *   $cache = new RedisCache($this->service(Redis::class));
 *
 * Storage is unambiguous by construction: a string is written verbatim unless
 * it begins with the marker, and only marked values are unserialized, so a
 * stored string can never come back as an object. Pass $allowedClasses to
 * bound what a marked payload is allowed to instantiate.
 *
 * Features:
 *   - Full PSR-16 compatibility
 *   - Atomic increment / decrement
 *   - Key prefix support via withPrefix()
 *
 * Redis data structure operations (Hash/List/Set/ZSet, queue, distributed
 * lock) live in the separate migears/data-structure package.
 *
 * @phpstan-consistent-constructor
 */
class RedisCache implements CacheInterface
{
    public const VERSION = '2.0.0';

    /** 无 phpredis 扩展时也可注入全局 mock（RedisMock），构造器接受任意对象（鸭子类型）。 */
    public const PIPELINE = 2;

    private readonly object $redis;
    private readonly LoggerInterface $logger;
    /** @var array<class-string>|bool */
    private readonly array|bool $allowedClasses;
    private string $prefix = '';

    /**
     * @param object $redis Already connected Redis instance; the caller owns the connection
     * @param LoggerInterface|null $logger
     * @param array<class-string>|bool $allowedClasses Classes unserialize() may instantiate.
     *        The default (true) keeps PSR-16 object support. Pass false, or the classes you
     *        actually cache, to stop a payload written by someone else from instantiating
     *        anything at all.
     */
    public function __construct(
        object $redis,
        ?LoggerInterface $logger = null,
        array|bool $allowedClasses = true,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->redis = $redis;
        $this->allowedClasses = $allowedClasses;
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

    /**
     * @param iterable<string> $keys
     * @return array<string, mixed>
     */
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

    /** @param iterable<string, mixed> $values */
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
            $pipe = $this->redis->multi(self::PIPELINE);
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
        $copy = new static($this->redis, $this->logger, $this->allowedClasses);
        $copy->prefix = $prefix;
        return $copy;
    }

    public function incr(string $key, int $step = 1): int
    {
        return $this->adjustCounter($key, $step);
    }

    public function decr(string $key, int $step = 1): int
    {
        return $this->adjustCounter($key, -$step);
    }

    // --- Internal ---

    /**
     * Move a counter by ARGV[1] inside Redis, atomically.
     *
     * INCR and DECR only accept a value Redis itself reads as an integer, and an
     * int stored by set() carries the marker in front of its serialized payload
     * (see serialize(), which cannot store it bare without a stored string
     * starting to read back as an int). So the script accepts both shapes,
     * writes the result back in the shape it found — a counter this cache wrote
     * stays an int, a bare integer another client wrote stays a string — and
     * re-applies the TTL that INCR itself would have left alone. Reading and
     * writing inside Redis is what keeps two processes from losing an
     * increment; doing it in PHP would be a read-modify-write.
     */
    private const COUNTER_SCRIPT = <<<'LUA'
    local value = redis.call('GET', KEYS[1])
    local step = tonumber(ARGV[1])
    local marked = true
    local current = 0

    if value == false then
      current = 0
    elseif string.sub(value, 1, 3) == '\0MG' then
      local digits = string.match(value, '^\0MGi:(%-?%d+);$')
      if digits == nil then
        return redis.error_reply('value is not an integer or out of range')
      end
      current = tonumber(digits)
    else
      if string.match(value, '^%-?%d+$') == nil then
        return redis.error_reply('value is not an integer or out of range')
      end
      current = tonumber(value)
      marked = false
    end

    local ttl = redis.call('PTTL', KEYS[1])
    local result = current + step

    if marked then
      redis.call('SET', KEYS[1], '\0MGi:' .. result .. ';')
    else
      redis.call('SET', KEYS[1], tostring(result))
    end

    if ttl > 0 then
      redis.call('PEXPIRE', KEYS[1], ttl)
    end

    return result
    LUA;

    private function adjustCounter(string $key, int $step): int
    {
        try {
            $result = $this->redis->eval(
                self::COUNTER_SCRIPT,
                [$this->prefix . $key, (string) $step],
                1
            );
            if (!is_int($result)) {
                throw new \RuntimeException("the value at \"{$key}\" is not an integer");
            }
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('RedisCache counter error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** Marker prefix for serialized values, so plain strings are never unserialized. */
    private const MARK = "\x00MG";

    /**
     * A string is stored raw so it stays readable, unless it begins with the
     * marker: then it is stored as a payload too and read back verbatim,
     * because the reader has no other way to tell the two apart. Without that
     * escape a string such as "\x00MGO:8:\"stdClass\":0:{}" would come back as
     * an object, which is the object-injection primitive.
     */
    private function serialize(mixed $value): string
    {
        return is_string($value) && !str_starts_with($value, self::MARK)
            ? $value
            : self::MARK . serialize($value);
    }

    /**
     * Only a value carrying the marker is a payload; anything else is the
     * string that was stored.
     */
    private function unserialize(string $value): mixed
    {
        if (!str_starts_with($value, self::MARK)) {
            return $value;
        }
        $payload = substr($value, strlen(self::MARK));
        $unserialized = @unserialize($payload, ['allowed_classes' => $this->allowedClasses]);
        return $unserialized !== false || $payload === serialize(false) ? $unserialized : $value;
    }

    private function ttlToSeconds(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable())->add($ttl)->getTimestamp() - time();
        }
        return $ttl;
    }
}
