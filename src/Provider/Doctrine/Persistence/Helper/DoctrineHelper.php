<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

use DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper\DoctrineHelperTest;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

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

        $className = mb_ltrim($subject, '\\');

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

    public static function setPrimaryKey(Table $table, string $columnName): void
    {
        /** @var non-empty-string $columnName */
        if (class_exists(PrimaryKeyConstraint::class)) {
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [new UnqualifiedName(Identifier::unquoted($columnName))], true));
        } else {
            $table->setPrimaryKey([$columnName]);
        }
    }

    public static function getReflectionPropertyValue(ClassMetadata $meta, string $name, object $entity): mixed
    {
        if (method_exists($meta, 'getPropertyAccessor')) {
            return $meta->getPropertyAccessor($name)?->getValue($entity);
        }

        return $meta->getReflectionProperty($name)?->getValue($entity);
    }

    public static function jsonStringType(): string
    {
        return \defined(Types::class.'::JSONB') ? Types::JSONB : Types::JSON;
    }

    /**
     * @return string[]
     */
    public static function jsonStringTypes(): array
    {
        return [Types::JSON, 'jsonb'];
    }

    /**
     * @return Type[]
     */
    public static function jsonTypes(): array
    {
        $jsonTypes = [Type::getType(Types::JSON)];
        if (\defined(Types::class.'::JSONB')) {
            $jsonTypes[] = Type::getType(Types::JSONB);
        }

        return $jsonTypes;
    }
}
