<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware;

use Doctrine\DBAL\Driver as BaseDriver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

final class DHMiddleware implements MiddlewareInterface
{
    public function wrap(BaseDriver $driver): BaseDriver
    {
        return new DHDriver($driver);
    }
}
