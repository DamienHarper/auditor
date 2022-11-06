<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

/**
 * @interal
 */
final class DHDriver implements DriverInterface
{
    private DriverInterface $driver;

    /** @var array<callable> */
    private array $flusherList = [];

    /**
     * @internal this driver can be only instantiated by its middleware
     */
    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * {@inheritDoc}
     */
    public function connect(array $params)
    {
        return new DHConnection(
            $this->driver->connect($params),
            $this
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform();
    }

    /**
     * {@inheritDoc}
     */
    public function getSchemaManager(DBALConnection $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        return $this->driver->getSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->driver->getExceptionConverter();
    }

    public function addDHFlusher(callable $flusher): void
    {
        $this->flusherList[] = $flusher;
    }

    public function resetDHFlusherList(): void
    {
        $this->flusherList = [];
    }

    public function getFlusherList(): array
    {
        return $this->flusherList;
    }
}
