<?php

declare(strict_types=1);

namespace MiGears\Cache\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Cache\RedisCache;

/** Minimal in-memory stand-in for phpredis, used to test serialization logic without the extension. */
class FakeRedis
{
    public array $data = [];
    public bool $delReturnsFalse = false;
    public bool $mgetReturnsFalse = false;
    public bool $pipelineFails = false;

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

    public function del(...$keys): int|false
    {
        if ($this->delReturnsFalse) {
            return false;
        }
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
    public function mget(array $keys): array|false
    {
        if ($this->mgetReturnsFalse) {
            return false;
        }
        return array_map(fn (string $k) => $this->data[$k] ?? false, $keys);
    }

    /** @param array<string, string> $values */
    public function mset(array $values): bool
    {
        // phpredis: MSET with no pairs is an error, so an empty batch is false.
        if ($values === []) {
            return false;
        }
        $this->data = array_merge($this->data, $values);
        return true;
    }

    public function multi(int $mode): self
    {
        return $this;
    }

    public function exec(): array
    {
        return $this->pipelineFails ? [false] : [true];
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

    public function testMarkerPrefixedStringCarryingAnObjectPayloadStaysString(): void
    {
        // The primitive the marker guard alone left open: the bytes after the
        // marker are a payload that unserialize() accepts.
        $payload = "\x00MG" . 'O:8:"stdClass":0:{}';
        $this->cache->set('marked-object', $payload);

        $got = $this->cache->get('marked-object');
        $this->assertSame($payload, $got);
        $this->assertIsString($got);
    }

    public function testMarkerPrefixedSerializedStringIsNotUnwrapped(): void
    {
        $payload = "\x00MG" . serialize('hello');
        $this->cache->set('marked-string', $payload);

        $this->assertSame($payload, $this->cache->get('marked-string'));
    }

    public function testMarkerPrefixedStringSurvivesGetMultiple(): void
    {
        $payload = "\x00MG" . 'a:1:{i:0;s:3:"foo";}';
        $this->cache->set('marked', $payload);

        $this->assertSame($payload, $this->cache->getMultiple(['marked'])['marked']);
    }

    public function testBinaryValueStartingWithMarkerRoundTrips(): void
    {
        $payload = "\x00MG" . "\x00\x01\x02\xff";
        $this->cache->set('binary', $payload);

        $this->assertSame($payload, $this->cache->get('binary'));
    }

    // --- allowed classes ---

    public function testAllowedClassesFalseBlocksInstantiation(): void
    {
        // A payload written straight into the store by someone else.
        $this->fake->data['poisoned'] = "\x00MG" . serialize(new \stdClass());

        $cache = new RedisCache($this->fake, allowedClasses: false);

        // unserialize() returns an __PHP_Incomplete_Class rather than the object.
        $this->assertNotInstanceOf(\stdClass::class, $cache->get('poisoned'));
    }

    public function testAllowedClassesFalseStillRoundTripsArrays(): void
    {
        $cache = new RedisCache($this->fake, allowedClasses: false);
        $cache->set('array', ['a' => 1, 'nested' => ['b' => 2]]);

        $this->assertSame(['a' => 1, 'nested' => ['b' => 2]], $cache->get('array'));
    }

    public function testWithPrefixKeepsAllowedClasses(): void
    {
        $cache = new RedisCache($this->fake, allowedClasses: false);
        $prefixed = $cache->withPrefix('p:');
        $this->fake->data['p:poisoned'] = "\x00MG" . serialize(new \stdClass());

        $this->assertNotInstanceOf(\stdClass::class, $prefixed->get('poisoned'));
    }

    // --- P2 regressions ---

    public function testGetMultipleAlignsByPositionForNonListArrays(): void
    {
        $this->cache->set('a', 'v-a');
        $this->cache->set('b', 'v-b');

        // Non-list keys: [1=>'a', 2=>'b'] must map by key, not by array index.
        $result = $this->cache->getMultiple([1 => 'a', 2 => 'b']);
        $this->assertSame(['a' => 'v-a', 'b' => 'v-b'], $result);
    }

    public function testGetMultipleHandlesAssociativeKeys(): void
    {
        $this->cache->set('x', 1);
        $this->cache->set('y', 2);

        $result = $this->cache->getMultiple(['foo' => 'x', 'bar' => 'y']);
        $this->assertSame(['x' => 1, 'y' => 2], $result);
    }

    public function testSetWithTTLZeroDeletesEntry(): void
    {
        $this->cache->set('k', 'boom');
        $this->cache->set('k', 'x', 0);

        $this->assertFalse($this->cache->has('k'));
        $this->assertNull($this->cache->get('k'));
    }

    public function testSetWithNegativeTTLDeletesEntry(): void
    {
        $this->cache->set('k', 'boom');
        $this->cache->set('k', 'x', -5);

        $this->assertFalse($this->cache->has('k'));
    }

    public function testDeletePropagatesRedisFailure(): void
    {
        $this->fake->delReturnsFalse = true;

        $this->assertFalse($this->cache->delete('missing'));
    }

    public function testDeleteWithZeroRemovedIsSuccess(): void
    {
        // 0 removed keys (absent) is still a successful no-op, not a failure.
        $this->assertTrue($this->cache->delete('missing'));
    }

    // --- P2-1 / P2-3 / P3-1 / P3-2 regressions ---

    public function testRejectsReservedCharactersInKeys(): void
    {
        $rejected = 0;
        foreach (['a{b', 'a}b', 'a(b', 'a)b', 'a/b', 'a\\b', 'a@b', 'a:b'] as $key) {
            try {
                $this->cache->set($key, 'value');
            } catch (\Psr\SimpleCache\InvalidArgumentException) {
                $rejected++;
            }
        }

        $this->assertSame(8, $rejected, 'Every reserved character must be rejected');
    }

    public function testAcceptsKeysWithoutReservedCharacters(): void
    {
        $this->cache->set('app.user-1_x!', 'value');
        $this->assertSame('value', $this->cache->get('app.user-1_x!'));
    }

    public function testRejectsNonStringKeysInBatchCalls(): void
    {
        $rejected = 0;
        try {
            $this->cache->setMultiple([0 => 'value']);
        } catch (\Psr\SimpleCache\InvalidArgumentException) {
            $rejected++;
        }

        $this->assertSame(1, $rejected, 'A non-string key must be rejected');
    }

    public function testGetMultipleReturnsCleanErrorWhenMgetFails(): void
    {
        $this->fake->mgetReturnsFalse = true;

        try {
            $this->cache->getMultiple(['a', 'b']);
            self::fail('Expected CacheException');
        } catch (\MiGears\Cache\Exception\CacheException $e) {
            $this->assertStringContainsString('mget', $e->getMessage());
        }
    }

    public function testSetMultipleReturnsFalseWhenAPipelineCommandFails(): void
    {
        $this->fake->pipelineFails = true;

        $this->assertFalse($this->cache->setMultiple(['a' => 1], 60));
    }

    public function testSetMultipleEmptyBatchIsTrue(): void
    {
        // FakeRedis::mset([]) mimics phpredis and returns false, so this only
        // passes if the empty batch is short-circuited before reaching mset().
        $this->assertTrue($this->cache->setMultiple([]));
    }

    public function testSetMultipleWithNonPositiveTtlDeletesEntries(): void
    {
        $this->cache->setMultiple(['a' => 1, 'b' => 2]);

        $this->assertTrue($this->cache->setMultiple(['a' => 9, 'b' => 9], 0));
        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
    }

    public function testWithPrefixWorksOnASubclassWithAnIncompatibleConstructor(): void
    {
        $cache = new class extends RedisCache {
            public function __construct()
            {
            }
        };

        $prefixed = $cache->withPrefix('p:');

        $this->assertInstanceOf(RedisCache::class, $prefixed);
        $this->assertNotSame($cache, $prefixed);
    }
}
