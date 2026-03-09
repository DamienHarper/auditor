<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware;

use Doctrine\DBAL\Driver as BaseDriver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

/**
 * @deprecated since auditor 4.x, to be removed in v5.0. Use damienharper/auditor-doctrine-provider instead.
 */
final class AuditorMiddleware implements MiddlewareInterface
{
    public function wrap(BaseDriver $driver): BaseDriver
    {
        return new AuditorDriver($driver);
    }
}
