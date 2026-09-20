<?php

declare(strict_types=1);

namespace MiGears\Cache;

use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;
use MiGears\Cache\Exception\CacheException;

/**
 * Cache interface — extends PSR-16 SimpleCacheInterface with additional features.
 *
 * Guarantees full PSR-16 compatibility, plus:
 *   - Atomic increment / decrement
 *   - getOrSet() convenience method
 *   - Key prefix support via withPrefix()
 */
interface CacheInterface extends SimpleCacheInterface
{
    /**
     * Gets a cache value, computing and storing it if missing.
     *
     * @param string $key Cache key
     * @param callable(): mixed $factory Callable that produces the value
     * @param null|int|\DateInterval $ttl Expiration: seconds, DateInterval, or null for never
     * @return mixed
     * @throws CacheException
     */
    public function getOrSet(string $key, callable $factory, ?int $ttl = null): mixed;

    /**
     * Returns a new cache instance with a key prefix applied.
     *
     * All keys will be prefixed with the given string, useful for
     * namespacing in shared cache backends.
     *
     * @param string $prefix Key prefix
     * @return static
     */
    public function withPrefix(string $prefix): static;

    /**
     * Increments a cache value atomically.
     *
     * @param string $key
     * @param int $step
     * @return int Value after increment
     * @throws CacheException
     */
    public function incr(string $key, int $step = 1): int;

    /**
     * Decrements a cache value atomically.
     *
     * @param string $key
     * @param int $step
     * @return int Value after decrement
     * @throws CacheException
     */
    public function decr(string $key, int $step = 1): int;
}
