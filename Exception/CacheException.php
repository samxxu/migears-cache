<?php

declare(strict_types=1);

namespace MiGears\Cache\Exception;

use Psr\SimpleCache\CacheException as PsrCacheException;

final class CacheException extends \RuntimeException implements PsrCacheException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
