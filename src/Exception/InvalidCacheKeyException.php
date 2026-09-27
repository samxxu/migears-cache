<?php

declare(strict_types=1);

namespace MiGears\Cache\Exception;

use Psr\SimpleCache\InvalidArgumentException;

/**
 * Thrown for a cache key that is not a string, or that contains one of the
 * characters PSR-16 reserves: {}()/\@:
 */
final class InvalidCacheKeyException extends \InvalidArgumentException implements InvalidArgumentException
{
}
