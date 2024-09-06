<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

use DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper\DoctrineHelperTest;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;

/**
 * @see DoctrineHelperTest
 *
 * @internal
 */
final class DoctrineHelper
{
    /**
     * Gets the real class name of a class name that could be a proxy.
     */
    public static function getRealClassName(object|string $subject): string
    {
        $subject = \is_object($subject) ? $subject::class : $subject;

        // __CG__: Doctrine Common Marker for Proxy (ODM < 2.0 and ORM < 3.0)
        // __PM__: Ocramius Proxy Manager (ODM >= 2.0)
        $positionCg = mb_strrpos($subject, '\__CG__\\');
        $positionPm = mb_strrpos($subject, '\__PM__\\');
        if (false === $positionCg && false === $positionPm) {
            return $subject;
        }

        if (false !== $positionCg) {
            return mb_substr($subject, $positionCg + 8);
        }

        $className = ltrim($subject, '\\');

        return mb_substr(
            $className,
            8 + $positionPm,
            mb_strrpos($className, '\\') - ($positionPm + 8)
        );
    }

    /**
     * TODO: remove this method when we drop support of doctrine/dbal 2.13.x.
     *
     * @throws Exception
     */
    public static function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return method_exists($connection, 'createSchemaManager')
            ? $connection->createSchemaManager()
            : $connection->getSchemaManager(); // @phpstan-ignore-line
    }

    /**
     * TODO: remove this method when we drop support of doctrine/dbal 2.13.x.
     *
     * @throws Exception
     */
    public static function introspectSchema(AbstractSchemaManager $schemaManager): Schema
    {
        return method_exists($schemaManager, 'introspectSchema')
            ? $schemaManager->introspectSchema()
            : $schemaManager->createSchema(); // @phpstan-ignore-line
    }

    /**
     * TODO: remove this method when we drop support of doctrine/dbal 2.13.x.
     *
     * @return array<string>
     *
     * @throws Exception
     */
    public static function getMigrateToSql(Connection $connection, Schema $fromSchema, Schema $toSchema): array
    {
        $platform = $connection->getDatabasePlatform();
        $schemaComparator = new Comparator($platform);

        if (method_exists($platform, 'getAlterSchemaSQL')) {
            return $platform->getAlterSchemaSQL(
                $schemaComparator->compareSchemas($fromSchema, $toSchema)
            );
        }

        return $fromSchema->getMigrateToSql($toSchema, $platform); // @phpstan-ignore-line
    }
}
