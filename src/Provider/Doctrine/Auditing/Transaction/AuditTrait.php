<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Transaction;

use DH\Auditor\Exception\MappingException;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\User\UserInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException as ORMMappingException;

trait AuditTrait
{
    /**
     * Returns the primary key value of an entity.
     *
     * @throws MappingException
     * @throws Exception
     * @throws ORMMappingException
     */
    private function id(EntityManagerInterface $entityManager, object $entity): mixed
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));

        try {
            $pk = $meta->getSingleIdentifierFieldName();
        } catch (ORMMappingException) {
            throw new MappingException(\sprintf('Composite primary keys are not supported (%s).', $entity::class));
        }

        if (isset($meta->fieldMappings[$pk])) {
            \assert(\is_string($meta->fieldMappings[$pk]['type']));
            $type = Type::getType($meta->fieldMappings[$pk]['type']);

            \assert(\is_object($meta->getReflectionProperty($pk)));

            return $this->value($entityManager, $type, $meta->getReflectionProperty($pk)->getValue($entity));
        }

        /*
         * Primary key is not part of fieldMapping.
         *
         * @see https://github.com/DamienHarper/auditor-bundle/issues/40
         * @see https://www.doctrine-project.org/projects/doctrine-orm/en/latest/tutorials/composite-primary-keys.html#identity-through-foreign-entities
         * We try to get it from associationMapping (will throw a MappingException if not available)
         */
        \assert(\is_object($meta->getReflectionProperty($pk)));
        $targetEntity = $meta->getReflectionProperty($pk)->getValue($entity);

        $mapping = $meta->getAssociationMapping($pk);

        \assert(\is_string($mapping['targetEntity']));
        $meta = $entityManager->getClassMetadata($mapping['targetEntity']);
        $pk = $meta->getSingleIdentifierFieldName();

        \assert(\is_string($meta->fieldMappings[$pk]['type']));
        $type = Type::getType($meta->fieldMappings[$pk]['type']);

        \assert(\is_object($targetEntity));
        \assert(\is_object($meta->getReflectionProperty($pk)));

        return $this->value($entityManager, $type, $meta->getReflectionProperty($pk)->getValue($targetEntity));
    }

    /**
     * Type converts the input value and returns it.
     *
     * @throws Exception
     * @throws ConversionException
     */
    private function value(EntityManagerInterface $entityManager, Type $type, mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        if (interface_exists(\UnitEnum::class) && $value instanceof \UnitEnum && property_exists($value, 'value')) { /** @phpstan-ignore-line */
            $value = $value->value;
        }

        $platform = $entityManager->getConnection()->getDatabasePlatform();

        switch (array_search($type::class, Type::getTypesMap(), true)) {
            case DoctrineHelper::getDoctrineType('BIGINT'):
            case 'ulid':
                // Ramsey UUID / Symfony UID (UUID/ULID)
                $convertedValue = (string) $value;  // @phpstan-ignore-line

                break;

            case DoctrineHelper::getDoctrineType('INTEGER'):
            case DoctrineHelper::getDoctrineType('SMALLINT'):
                $convertedValue = (int) $value; // @phpstan-ignore-line

                break;

            case DoctrineHelper::getDoctrineType('DECIMAL'):
            case DoctrineHelper::getDoctrineType('FLOAT'):
            case DoctrineHelper::getDoctrineType('BOOLEAN'):
                $convertedValue = $type->convertToPHPValue($value, $platform);

                break;

            case 'uuid_binary':
            case 'uuid_binary_ordered_time':
            case 'uuid':
            case DoctrineHelper::getDoctrineType('BLOB'):
            case DoctrineHelper::getDoctrineType('BINARY'):
                if (\is_resource($value)) {
                    // let's replace resources with a "simple" representation: resourceType#resourceId
                    $convertedValue = get_resource_type($value).'#'.get_resource_id($value);
                } else {
                    $convertedValue = $type->convertToDatabaseValue($value, $platform);
                }

                break;

            case DoctrineHelper::getDoctrineType('JSON'):
                return $value;

            default:
                $convertedValue = $type->convertToDatabaseValue($value, $platform);
        }

        return $convertedValue;
    }

    /**
     * Computes a usable diff.
     *
     * @throws MappingException
     * @throws Exception
     * @throws ConversionException
     * @throws ORMMappingException
     */
    private function diff(EntityManagerInterface $entityManager, object $entity, array $changeset): array
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $diff = [
            '@source' => $this->summarize($entityManager, $entity),
        ];

        foreach ($changeset as $fieldName => [$old, $new]) {
            $o = null;
            $n = null;

            // skip if $old and $new are null
            if (null === $old && null === $new) {
                continue;
            }

            if (
                !isset($meta->embeddedClasses[$fieldName])
                && $meta->hasField($fieldName)
                && $this->provider->isAuditedField($entity, $fieldName)
            ) {
                $mapping = $meta->fieldMappings[$fieldName];
                \assert(\is_string($mapping['type']));
                $type = Type::getType($mapping['type']);
                $o = $this->value($entityManager, $type, $old);
                $n = $this->value($entityManager, $type, $new);
            } elseif (
                $meta->hasAssociation($fieldName)
                && $meta->isSingleValuedAssociation($fieldName)
                && $this->provider->isAuditedField($entity, $fieldName)
            ) {
                $o = $this->summarize($entityManager, $old);
                $n = $this->summarize($entityManager, $new);
            }

            if ($o !== $n) {
                $diff[$fieldName] = [
                    'new' => $n,
                    'old' => $o,
                ];
            }
        }

        return $diff;
    }

    /**
     * Returns an array describing an entity.
     *
     * @throws MappingException
     * @throws Exception
     * @throws ORMMappingException
     */
    private function summarize(EntityManagerInterface $entityManager, ?object $entity = null, array $extra = []): ?array
    {
        if (null === $entity) {
            return null;
        }

        $entityManager->getUnitOfWork()->initializeObject($entity); // ensure that proxies are initialized
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));

        $pkValue = $extra['id'] ?? $this->id($entityManager, $entity);
        $pkName = $meta->getSingleIdentifierFieldName();

        if (method_exists($entity, '__toString')) {
            try {
                $label = (string) $entity;
            } catch (\Throwable) {
                $label = DoctrineHelper::getRealClassName($entity).(null === $pkValue ? '' : '#'.$pkValue);
            }
        } else {
            $label = DoctrineHelper::getRealClassName($entity).(null === $pkValue ? '' : '#'.$pkValue);
        }

        if ('id' !== $pkName) {
            $extra['pkName'] = $pkName;
        }

        return [
            $pkName => $pkValue,
            'class' => $meta->name,
            'label' => $label,
            'table' => $meta->getTableName(),
        ] + $extra;
    }

    /**
     * Blames an audit operation.
     *
     * @return array{client_ip: null|string, user_firewall: null|string, user_fqdn: null|string, user_id: null|string, username: null|string}
     */
    private function blame(): array
    {
        $user_id = null;
        $username = null;
        $client_ip = null;
        $user_fqdn = null;
        $user_firewall = null;

        $securityProvider = $this->provider->getAuditor()->getConfiguration()->getSecurityProvider();
        if (null !== $securityProvider) {
            [$client_ip, $user_firewall] = $securityProvider();
        }

        $userProvider = $this->provider->getAuditor()->getConfiguration()->getUserProvider();
        $user = null === $userProvider ? null : $userProvider();
        if ($user instanceof UserInterface) {
            $user_id = $user->getIdentifier();
            $username = $user->getUsername();
            $user_fqdn = DoctrineHelper::getRealClassName($user);
        }

        return [
            'client_ip' => $client_ip,
            'user_firewall' => $user_firewall,
            'user_fqdn' => $user_fqdn,
            'user_id' => $user_id,
            'username' => $username,
        ];
    }
}
