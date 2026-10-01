<?php

declare(strict_types=1);

namespace MiGears\Cache;

use Psr\Log\LoggerInterface;
use MiGears\Cache\Exception\CacheException;
use MiGears\Cache\Exception\InvalidCacheKeyException;

/**
 * In-memory array cache implementation.
 *
 * Useful for unit testing, development environments, and caching within
 * a single request lifecycle.
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expire: int|null}> */
    private array $data = [];
    private readonly LoggerInterface $logger;
    private string $prefix = '';

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertValidKey($key);
        try {
            $pkey = $this->prefix . $key;
            if (!$this->hasInternal($pkey)) {
                return $default;
            }
            return $this->data[$pkey]['value'];
        } catch (\Throwable $e) {
            $this->logError('ArrayCache get error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->assertValidKey($key);
        try {
            $this->data[$this->prefix . $key] = [
                'value' => $value,
                'expire' => $this->ttlToExpire($ttl),
            ];
            return true;
        } catch (\Throwable $e) {
            $this->logError('ArrayCache set error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function delete(string $key): bool
    {
        $this->assertValidKey($key);
        // No try/catch here: $this->data is a typed array, so unset() cannot throw,
        // which makes the sibling methods' catch branch unreachable for this body.
        unset($this->data[$this->prefix . $key]);
        return true;
    }

    public function has(string $key): bool
    {
        $this->assertValidKey($key);
        try {
            return $this->hasInternal($this->prefix . $key);
        } catch (\Throwable $e) {
            $this->logError('ArrayCache has error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function clear(): bool
    {
        try {
            if ($this->prefix === '') {
                $this->data = [];
            } else {
                $prefix = $this->prefix;
                $this->data = array_filter(
                    $this->data,
                    fn($k) => !str_starts_with($k, $prefix),
                    ARRAY_FILTER_USE_KEY
                );
            }
            return true;
        } catch (\Throwable $e) {
            $this->logError('ArrayCache clear error', ['exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param iterable<string> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): array
    {
        $keyList = is_array($keys) ? array_values($keys) : iterator_to_array($keys, false);
        foreach ($keyList as $key) {
            $this->assertValidKey($key);
        }
        try {
            $result = [];
            foreach ($keyList as $key) {
                $result[$key] = $this->get($key, $default);
            }
            return $result;
        } catch (\Throwable $e) {
            $this->logError('ArrayCache getMultiple error', ['exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @param iterable<string, mixed> $values */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        try {
            foreach ($values as $key => $value) {
                $this->assertValidKey($key);
                $this->set($key, $value, $ttl);
            }
            return true;
        } catch (InvalidCacheKeyException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError('ArrayCache setMultiple error', ['exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function deleteMultiple(iterable $keys): bool
    {
        try {
            foreach ($keys as $key) {
                $this->assertValidKey($key);
                $this->delete($key);
            }
            return true;
        } catch (InvalidCacheKeyException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError('ArrayCache deleteMultiple error', ['exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getOrSet(string $key, callable $factory, null|int|\DateInterval $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        $value = $factory();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function withPrefix(string $prefix): static
    {
        // clone, not `new static(...)`: a subclass with an incompatible
        // constructor must not make prefixing fail, and cloning keeps the
        // already-initialized state without re-reading constructor arguments.
        $copy = clone $this;
        $copy->data = &$this->data;
        $copy->prefix = $prefix;
        return $copy;
    }

    public function incr(string $key, int $step = 1): int
    {
        $this->assertValidKey($key);
        try {
            $pkey = $this->prefix . $key;
            if ($this->hasInternal($pkey)) {
                $current = $this->counterValue($pkey, $key) + $step;
                $this->data[$pkey]['value'] = $current;
            } else {
                $current = $step;
                $this->data[$pkey] = ['value' => $current, 'expire' => null];
            }
            return $current;
        } catch (\Throwable $e) {
            $this->logError('ArrayCache incr error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function decr(string $key, int $step = 1): int
    {
        $this->assertValidKey($key);
        try {
            $pkey = $this->prefix . $key;
            if ($this->hasInternal($pkey)) {
                $current = $this->counterValue($pkey, $key) - $step;
                $this->data[$pkey]['value'] = $current;
            } else {
                $current = -$step;
                $this->data[$pkey] = ['value' => $current, 'expire' => null];
            }
            return $current;
        } catch (\Throwable $e) {
            $this->logError('ArrayCache decr error', ['key' => $key, 'exception' => $e]);
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }

    // --- Internal ---

    /**
     * PSR-16 reserves these characters and requires an InvalidArgumentException.
     *
     * @param mixed $key
     */
    private function assertValidKey(mixed $key): void
    {
        if (!is_string($key) || strpbrk($key, '{}()/\\@:') !== false) {
            throw new InvalidCacheKeyException(
                'Invalid cache key: expected a string without the reserved characters {}()/\\@:'
            );
        }
    }

    /**
     * The stored counter as an int, or a failure when the value is not an integer.
     *
     * `RedisCache::adjustCounter()` answers a non-integer with `the value at "…" is not an integer`, and the
     * two implementations are meant to be interchangeable, so this side says the same thing rather than
     * casting. The cast was not harmless: `(int) 'abc'` is 0, so a counter that had been overwritten with a
     * non-numeric value silently restarted from the step. An integer string counts as an integer, as it does
     * in Redis; a float does not, because a counter holds whole numbers.
     *
     * 把存着的计数读成 int；不是整数就报错。`RedisCache::adjustCounter()` 对非整数答的是
     * 「the value at "…" is not an integer」，而两个实现本就应该可以互换，因此这一侧答同样的话，而不是
     * 强转。那个强转并非无害：`(int) 'abc'` 是 0，于是被非数值覆盖过的计数器会悄悄从步长重新开始。
     * 整数字符串视同整数，与 Redis 一致；浮点不算，因为计数器装的是整数。
     */
    private function counterValue(string $pkey, string $key): int
    {
        $value = $this->data[$pkey]['value'];

        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1) {
            return (int) $value;
        }

        throw new \RuntimeException(sprintf('the value at "%s" is not an integer', $key));
    }

    private function hasInternal(string $pkey): bool
    {
        if (!isset($this->data[$pkey])) {
            return false;
        }
        $expire = $this->data[$pkey]['expire'];
        if ($expire !== null && $expire <= time()) {
            unset($this->data[$pkey]);
            return false;
        }
        return true;
    }

    private function ttlToExpire(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }
        return time() + $ttl;
    }

    /** @param array<string, mixed> $context */
    private function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}
