<?php

declare(strict_types=1);

namespace MiGears\Cache\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Cache\RedisCache;

/**
 * @requires extension redis
 * @group integration
 */
class RedisCacheTest extends TestCase
{
    private RedisCache $cache;

    protected function setUp(): void
    {
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);
        $dbindex = (int) (getenv('REDIS_DB') ?: 15); // Use db 15 for testing

        try {
            $redis = new \Redis();
            $redis->connect($host, $port);
            $redis->select($dbindex);
            $this->cache = new RedisCache($redis);
            $this->cache->clear();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis server not available: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->cache)) {
            $this->cache->clear();
        }
    }

    // --- get / set ---

    public function testSetAndGetString(): void
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

    public function testSetWithDateIntervalTtl(): void
    {
        $interval = new \DateInterval('PT1H');
        $this->assertTrue($this->cache->set('foo', 'bar', $interval));
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function testSetWithMonthDateIntervalTtl(): void
    {
        // Regression: P1M (months) must not be silently treated as 0 seconds
        $interval = new \DateInterval('P1M');
        $this->assertTrue($this->cache->set('foo', 'bar', $interval));
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function testSetArrayValue(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $this->cache->set('foo', $data);
        $this->assertSame($data, $this->cache->get('foo'));
    }

    public function testSetIntValue(): void
    {
        $this->cache->set('foo', 42);
        $this->assertSame(42, $this->cache->get('foo'));
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

    // --- getMultiple / setMultiple ---

    public function testGetMultiple(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);

        $result = $this->cache->getMultiple(['a', 'b', 'c']);
        $this->assertSame(1, $result['a']);
        $this->assertSame(2, $result['b']);
        $this->assertNull($result['c']);
    }

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

    // --- incr / decr ---

    public function testIncr(): void
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

    public function testDecr(): void
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

    // --- Constructor ---

    public function testConstructWithRedisInstance(): void
    {
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);

        $redis = new \Redis();
        $redis->connect($host, $port);

        $cache = new RedisCache($redis);
        $cache->set('test_instance', 'value');
        $this->assertSame('value', $cache->get('test_instance'));
        $cache->delete('test_instance');
    }
}
