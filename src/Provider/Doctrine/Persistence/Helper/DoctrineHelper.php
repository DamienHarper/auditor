<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

use DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper\DoctrineHelperTest;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
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
     *
     * Doctrine ORM uses __CG__ (Common Gateway) marker for proxy classes.
     * Note: With PHP 8.4+, Doctrine uses native lazy objects instead.
     */
    public static function getRealClassName(object|string $subject): string
    {
        $subject = \is_object($subject) ? $subject::class : $subject;

        // __CG__: Doctrine Proxy Marker (Doctrine\Persistence\Proxy::MARKER)
        $position = mb_strrpos($subject, '\__CG__\\');
        if (false === $position) {
            return $subject;
        }

        return mb_substr($subject, $position + 8);
    }

    public static function setPrimaryKey(Table $table, string $columnName): void
    {
        /** @var non-empty-string $columnName */
        $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [new UnqualifiedName(Identifier::unquoted($columnName))], true));
    }

    public static function getReflectionPropertyValue(ClassMetadata $meta, string $name, object $entity): mixed
    {
        return $meta->getPropertyAccessor($name)?->getValue($entity);
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
