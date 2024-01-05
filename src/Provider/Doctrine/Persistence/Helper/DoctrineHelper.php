<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

use Composer\Autoload\ClassLoader;
use DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper\DoctrineHelperTest;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Setup;
use InvalidArgumentException;
use ReflectionClass;

/**
 * @see DoctrineHelperTest
 *
 * @internal
 */
final class DoctrineHelper
{
    /**
     * Gets the real class name of a class name that could be a proxy.
     *
     * @param object|string $subject
     *
     * credits
     * https://github.com/api-platform/core/blob/master/src/Util/ClassInfoTrait.php
     */
    public static function getRealClassName($subject): string
    {
        $subject = \is_object($subject) ? \get_class($subject) : $subject;

        // __CG__: Doctrine Common Marker for Proxy (ODM < 2.0 and ORM < 3.0)
        // __PM__: Ocramius Proxy Manager (ODM >= 2.0)
        $positionCg = mb_strrpos($subject, '\\__CG__\\');
        $positionPm = mb_strrpos($subject, '\\__PM__\\');
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

    public static function getDoctrineType(string $type): string
    {
        if (!\defined(Types::class.'::'.$type)) {
            throw new InvalidArgumentException(sprintf('Undefined Doctrine type "%s"', $type));
        }

        \assert(\is_string(\constant(Types::class.'::'.$type)));

        return \constant(Types::class.'::'.$type);
    }

    /**
     * @param QueryBuilder|Statement $statement
     *
     * @throws Exception
     */
    public static function executeStatement($statement): void
    {
        if (method_exists($statement, 'executeStatement')) {
            $statement->executeStatement();
        } else {
            $statement->execute();
        }
    }

    /**
     * @param QueryBuilder|Statement $statement
     *
     * @return int|Result|string
     *
     * @throws Exception
     */
    public static function executeQuery($statement)
    {
        if (method_exists($statement, 'executeQuery')) {
            return $statement->executeQuery();
        }

        return $statement->execute();
    }

    public static function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return method_exists($connection, 'createSchemaManager')
            ? $connection->createSchemaManager()
            : $connection->getSchemaManager();
    }

    public static function introspectSchema(AbstractSchemaManager $schemaManager): Schema
    {
        return method_exists($schemaManager, 'introspectSchema')
            ? $schemaManager->introspectSchema()
            : $schemaManager->createSchema();
    }

    /**
     * @return array<string>
     *
     * @throws Exception
     */
    public static function getMigrateToSql(Connection $connection, Schema $fromSchema, Schema $toSchema): array
    {
        $schemaComparator = new Comparator();
        $platform = $connection->getDatabasePlatform();

        if (method_exists($platform, 'getAlterSchemaSQL')) {
            return $platform->getAlterSchemaSQL(
                $schemaComparator->compareSchemas($fromSchema, $toSchema)
            );
        }

        return $fromSchema->getMigrateToSql($toSchema, $platform);
    }

    public static function createAttributeMetadataConfiguration(array $paths, bool $isDevMode = false): Configuration
    {
        if (class_exists(ORMSetup::class)) {
            return ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);
        }

        return Setup::createAttributeMetadataConfiguration($paths, $isDevMode);
    }

    public static function createAnnotationMetadataConfiguration(array $paths, bool $isDevMode = false): Configuration
    {
        if (class_exists(ORMSetup::class)) {
            // @phpstan-ignore-next-line
            if (method_exists(AnnotationRegistry::class, 'registerLoader')) {
                AnnotationRegistry::registerLoader('class_exists');
            }

            return ORMSetup::createAnnotationMetadataConfiguration($paths, $isDevMode);
        }

        return Setup::createAnnotationMetadataConfiguration($paths, $isDevMode, null, null, false);
    }

    public static function getEntityManagerFromOnFlushEventArgs(OnFlushEventArgs $args): EntityManagerInterface
    {
        return method_exists($args, 'getObjectManager') ? $args->getObjectManager() : $args->getEntityManager();
    }

    public static function getVendorDir(): string
    {
        $reflection = new ReflectionClass(ClassLoader::class);
        $filename = $reflection->getFileName();
        \assert(\is_string($filename));

        return \dirname($filename, 2);
    }
}
