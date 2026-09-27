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
    private readonly ?LoggerInterface $logger;
    private string $prefix = '';

    public function __construct(?LoggerInterface $logger = null)
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
                $current = (int) $this->data[$pkey]['value'] + $step;
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
                $current = (int) $this->data[$pkey]['value'] - $step;
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
        $this->logger?->error($message, $context);
    }
}
