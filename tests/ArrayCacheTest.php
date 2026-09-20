<?php

declare(strict_types=1);

namespace MiGears\Cache\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Cache\ArrayCache;

class ArrayCacheTest extends TestCase
{
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    // --- get / set ---

    public function testSetAndGet(): void
    {
        $this->assertTrue($this->cache->set('foo', 'bar'));
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function testGetNonExistentReturnsDefault(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
        $this->assertSame('default', $this->cache->get('nonexistent', 'default'));
    }

    public function testSetOverwritesExisting(): void
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set('foo', 'baz');
        $this->assertSame('baz', $this->cache->get('foo'));
    }

    public function testSetWithTtl(): void
    {
        $this->assertTrue($this->cache->set('foo', 'bar', 3600));
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function testZeroTtlExpiresImmediately(): void
    {
        $this->cache->set('foo', 'bar', 0);
        $this->assertNull($this->cache->get('foo'));
    }

    public function testSetNullValue(): void
    {
        $this->cache->set('foo', null);
        $this->assertNull($this->cache->get('foo'));
        // Note: has should return true because the value exists (even though it's null)
        $this->assertTrue($this->cache->has('foo'));
    }

    public function testSetArrayValue(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $this->cache->set('foo', $data);
        $this->assertSame($data, $this->cache->get('foo'));
    }

    public function testSetObjectValue(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $this->cache->set('foo', $obj);
        $this->assertEquals($obj, $this->cache->get('foo'));
    }

    public function testSetIntValue(): void
    {
        $this->cache->set('foo', 42);
        $this->assertSame(42, $this->cache->get('foo'));
    }

    public function testSetBoolValue(): void
    {
        $this->cache->set('foo', true);
        $this->assertTrue($this->cache->get('foo'));

        $this->cache->set('bar', false);
        $this->assertFalse($this->cache->get('bar'));
    }

    // --- delete ---

    public function testDelete(): void
    {
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->delete('foo'));
        $this->assertFalse($this->cache->has('foo'));
    }

    public function testDeleteNonExistent(): void
    {
        $this->assertTrue($this->cache->delete('nonexistent'));
    }

    // --- has ---

    public function testHas(): void
    {
        $this->assertFalse($this->cache->has('foo'));
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->has('foo'));
    }

    // --- clear ---

    public function testClear(): void
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set('baz', 'qux');
        $this->assertTrue($this->cache->clear());
        $this->assertFalse($this->cache->has('foo'));
        $this->assertFalse($this->cache->has('baz'));
    }

    // --- getMultiple ---

    public function testGetMultiple(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);

        $result = $this->cache->getMultiple(['a', 'b', 'c']);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => null], $result);
    }

    public function testGetMultipleWithDefault(): void
    {
        $this->cache->set('a', 1);
        $result = $this->cache->getMultiple(['a', 'b'], 'N/A');
        $this->assertSame(['a' => 1, 'b' => 'N/A'], $result);
    }

    public function testGetMultipleWithEmptyKeys(): void
    {
        $result = $this->cache->getMultiple([]);
        $this->assertSame([], $result);
    }

    // --- setMultiple ---

    public function testSetMultiple(): void
    {
        $values = ['a' => 1, 'b' => 2, 'c' => 3];
        $this->assertTrue($this->cache->setMultiple($values));
        $this->assertSame(1, $this->cache->get('a'));
        $this->assertSame(2, $this->cache->get('b'));
        $this->assertSame(3, $this->cache->get('c'));
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = ['a' => 1, 'b' => 2];
        $this->assertTrue($this->cache->setMultiple($values, 3600));
        $this->assertSame(1, $this->cache->get('a'));
        $this->assertSame(2, $this->cache->get('b'));
    }

    public function testSetMultipleEmpty(): void
    {
        $this->assertTrue($this->cache->setMultiple([]));
    }

    // --- deleteMultiple ---

    public function testDeleteMultiple(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $this->cache->set('c', 3);

        $this->assertTrue($this->cache->deleteMultiple(['a', 'c']));
        $this->assertFalse($this->cache->has('a'));
        $this->assertTrue($this->cache->has('b'));
        $this->assertFalse($this->cache->has('c'));
    }

    public function testDeleteMultipleEmpty(): void
    {
        $this->assertTrue($this->cache->deleteMultiple([]));
    }

    // --- incr ---

    public function testIncrOnNewKey(): void
    {
        $result = $this->cache->incr('counter');
        $this->assertSame(1, $result);
    }

    public function testIncrOnExistingKey(): void
    {
        $this->cache->set('counter', 10);
        $result = $this->cache->incr('counter');
        $this->assertSame(11, $result);
    }

    public function testIncrWithStep(): void
    {
        $this->cache->set('counter', 10);
        $result = $this->cache->incr('counter', 5);
        $this->assertSame(15, $result);
    }

    public function testIncrMultipleTimes(): void
    {
        $this->cache->incr('counter');
        $this->cache->incr('counter');
        $result = $this->cache->incr('counter');
        $this->assertSame(3, $result);
    }

    // --- decr ---

    public function testDecrOnNewKey(): void
    {
        $result = $this->cache->decr('counter');
        $this->assertSame(-1, $result);
    }

    public function testDecrOnExistingKey(): void
    {
        $this->cache->set('counter', 10);
        $result = $this->cache->decr('counter');
        $this->assertSame(9, $result);
    }

    public function testDecrWithStep(): void
    {
        $this->cache->set('counter', 10);
        $result = $this->cache->decr('counter', 3);
        $this->assertSame(7, $result);
    }

    public function testDecrToNegative(): void
    {
        $this->cache->set('counter', 0);
        $result = $this->cache->decr('counter', 5);
        $this->assertSame(-5, $result);
    }

    // --- getOrSet ---

    public function testGetOrSetWithMiss(): void
    {
        $called = 0;
        $result = $this->cache->getOrSet('foo', function () use (&$called) {
            $called++;
            return 'bar';
        });
        $this->assertSame('bar', $result);
        $this->assertSame(1, $called);
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function testGetOrSetWithHit(): void
    {
        $this->cache->set('foo', 'cached');
        $called = 0;
        $result = $this->cache->getOrSet('foo', function () use (&$called) {
            $called++;
            return 'computed';
        });
        $this->assertSame('cached', $result);
        $this->assertSame(0, $called);
    }

    public function testGetOrSetWithTtl(): void
    {
        $result = $this->cache->getOrSet('foo', fn() => 'bar', 3600);
        $this->assertSame('bar', $result);
        $this->assertTrue($this->cache->has('foo'));
    }

    public function testGetOrSetWithNullValue(): void
    {
        $called = 0;
        $result = $this->cache->getOrSet('foo', function () use (&$called) {
            $called++;
            return null;
        });
        $this->assertNull($result);
        $this->assertSame(1, $called);
        // Second call should still hit cache (null is a valid cached value)
        $result2 = $this->cache->getOrSet('foo', function () use (&$called) {
            $called++;
            return 'other';
        });
        $this->assertNull($result2);
        $this->assertSame(1, $called);
    }

    // --- withPrefix ---

    public function testWithPrefixIsolatesKeys(): void
    {
        $prefixed = $this->cache->withPrefix('app:');
        $this->cache->set('foo', 'global');
        $prefixed->set('foo', 'prefixed');

        $this->assertSame('global', $this->cache->get('foo'));
        $this->assertSame('prefixed', $prefixed->get('foo'));
    }

    public function testWithPrefixHas(): void
    {
        $prefixed = $this->cache->withPrefix('ns:');
        $prefixed->set('key', 'value');

        $this->assertTrue($prefixed->has('key'));
        $this->assertFalse($this->cache->has('key'));
    }

    public function testWithPrefixDelete(): void
    {
        $prefixed = $this->cache->withPrefix('ns:');
        $prefixed->set('foo', 'bar');
        $prefixed->delete('foo');
        $this->assertFalse($prefixed->has('foo'));
    }

    public function testWithPrefixClearOnlyClearsPrefixedKeys(): void
    {
        $this->cache->set('global', 'value');
        $prefixed = $this->cache->withPrefix('app:');
        $prefixed->set('a', 1);
        $prefixed->set('b', 2);

        $prefixed->clear();

        $this->assertFalse($prefixed->has('a'));
        $this->assertFalse($prefixed->has('b'));
        $this->assertTrue($this->cache->has('global'));
    }

    public function testWithPrefixIncrDecr(): void
    {
        $prefixed = $this->cache->withPrefix('cnt:');
        $result = $prefixed->incr('counter');
        $this->assertSame(1, $result);
        $this->assertFalse($this->cache->has('counter'));
        $decrResult = $prefixed->decr('counter');
        $this->assertSame(0, $decrResult);
        $this->assertSame(0, $prefixed->get('counter'));
    }

    public function testWithPrefixMultiple(): void
    {
        $prefixed = $this->cache->withPrefix('ns:');
        $prefixed->setMultiple(['a' => 1, 'b' => 2]);
        $result = $prefixed->getMultiple(['a', 'b', 'c']);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => null], $result);

        $prefixed->deleteMultiple(['a', 'b']);
        $this->assertFalse($prefixed->has('a'));
        $this->assertFalse($prefixed->has('b'));
    }

    public function testWithPrefixReturnsNewInstance(): void
    {
        $prefixed = $this->cache->withPrefix('ns:');
        $this->assertNotSame($this->cache, $prefixed);
        $this->assertInstanceOf(ArrayCache::class, $prefixed);
    }

    // --- DateInterval TTL ---

    public function testSetWithDateIntervalTtl(): void
    {
        $interval = new \DateInterval('PT1H'); // 1 hour
        $this->assertTrue($this->cache->set('foo', 'bar', $interval));
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function testSetWithMonthDateIntervalTtl(): void
    {
        // Regression: P1M (months) must not be silently treated as 0 seconds
        $interval = new \DateInterval('P1M');
        $this->assertTrue($this->cache->set('foo', 'bar', $interval));

        $ref = new \ReflectionProperty(ArrayCache::class, 'data');
        $expire = $ref->getValue($this->cache)['foo']['expire'];
        $this->assertNotNull($expire);
        $this->assertGreaterThan(time() + 25 * 86400, $expire);
    }

    public function testSetMultipleWithDateIntervalTtl(): void
    {
        $interval = new \DateInterval('PT30M');
        $this->assertTrue($this->cache->setMultiple(['a' => 1], $interval));
        $this->assertSame(1, $this->cache->get('a'));
    }

    public function testGetOrSetWithDateIntervalTtl(): void
    {
        $interval = new \DateInterval('PT10M');
        $result = $this->cache->getOrSet('foo', fn() => 'bar', $interval);
        $this->assertSame('bar', $result);
    }

    // --- PSR-16 compliance ---

    public function testImplementsPsr16Interface(): void
    {
        $this->assertInstanceOf(\Psr\SimpleCache\CacheInterface::class, $this->cache);
    }

    // --- Edge cases ---

    public function testEmptyStringKey(): void
    {
        $this->cache->set('', 'value');
        $this->assertSame('value', $this->cache->get(''));
    }

    public function testSpecialCharsKey(): void
    {
        $key = 'key:with:special:chars!@#$%';
        $this->cache->set($key, 'value');
        $this->assertSame('value', $this->cache->get($key));
    }

    public function testLongStringValue(): void
    {
        $long = str_repeat('a', 10000);
        $this->cache->set('long', $long);
        $this->assertSame($long, $this->cache->get('long'));
    }
}
