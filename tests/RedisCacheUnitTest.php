<?php

declare(strict_types=1);

namespace MiGears\Cache\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Cache\RedisCache;

/** Minimal in-memory stand-in for phpredis, used to test serialization logic without the extension. */
class FakeRedis
{
    public array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? false;
    }

    public function set(string $key, string $value): bool
    {
        $this->data[$key] = $value;
        return true;
    }

    public function setex(string $key, int $ttl, string $value): bool
    {
        $this->data[$key] = $value;
        return true;
    }

    public function exists(string $key): int
    {
        return isset($this->data[$key]) ? 1 : 0;
    }

    public function del(...$keys): int
    {
        $count = 0;
        foreach ($keys as $key) {
            if (isset($this->data[$key])) {
                unset($this->data[$key]);
                $count++;
            }
        }
        return $count;
    }

    /** @param string[] $keys */
    public function mget(array $keys): array
    {
        return array_map(fn (string $k) => $this->data[$k] ?? false, $keys);
    }

    /** @param array<string, string> $values */
    public function mset(array $values): bool
    {
        $this->data = array_merge($this->data, $values);
        return true;
    }

    public function multi(int $mode): self
    {
        return $this;
    }

    public function exec(): array
    {
        return [];
    }

    public function flushDB(): bool
    {
        $this->data = [];
        return true;
    }
}

/**
 * Regression coverage for the P0 object-injection finding: plain strings,
 * including ones shaped like serialized objects, must never be unserialized.
 *
 * Runs in the default suite (no @group integration), unlike RedisCacheTest
 * which needs a live Redis server.
 */
class RedisCacheUnitTest extends TestCase
{
    private FakeRedis $fake;
    private RedisCache $cache;

    protected function setUp(): void
    {
        $this->fake = new FakeRedis();
        $this->cache = new RedisCache($this->fake);
    }

    public function testStringShapedLikeSerializedObjectStaysString(): void
    {
        $payload = 'O:8:"stdClass":0:{}';
        $this->cache->set('evil', $payload);
        $this->assertSame($payload, $this->cache->get('evil'));
        $this->assertIsString($this->cache->get('evil'));
    }

    public function testStringShapedLikeSerializedArrayStaysString(): void
    {
        $payload = 'a:1:{i:0;s:3:"foo";}';
        $this->cache->set('tricky', $payload);
        $this->assertSame($payload, $this->cache->get('tricky'));
    }

    public function testNonStringValuesRoundTrip(): void
    {
        $this->cache->set('array', ['a' => 1, 'b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $this->cache->get('array'));

        $this->cache->set('int', 42);
        $this->assertSame(42, $this->cache->get('int'));

        $this->cache->set('bool-false', false);
        $this->assertSame(false, $this->cache->get('bool-false'));

        $this->cache->set('null', null);
        $this->assertSame(null, $this->cache->get('null'));
    }

    public function testGetMultipleDoesNotUnserializePlainStrings(): void
    {
        $this->cache->set('a', 'O:8:"stdClass":0:{}');
        $this->cache->set('b', ['x' => 1]);

        $result = $this->cache->getMultiple(['a', 'b']);
        $this->assertSame('O:8:"stdClass":0:{}', $result['a']);
        $this->assertSame(['x' => 1], $result['b']);
    }

    public function testStoredSerializedStringStillRoundTrips(): void
    {
        // A value that legitimately serializes to a string must come back as that string.
        $this->cache->set('serialized-string', serialize('already-serialized'));
        $this->assertSame(serialize('already-serialized'), $this->cache->get('serialized-string'));
    }

    public function testStringStartingWithMarkerIsTreatedAsString(): void
    {
        // Edge case: a plain string that happens to begin with the marker byte.
        $payload = "\x00MG" . 'plain';
        $this->cache->set('edge', $payload);
        $this->assertSame($payload, $this->cache->get('edge'));
    }
}
