<?php

namespace DH\Auditor\Provider\Doctrine\Auditing\Transaction;

use DH\Auditor\Exception\MappingException;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\User\UserInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException as ORMMappingException;

trait AuditTrait
{
    /**
     * Returns the primary key value of an entity.
     *
     * @param object $entity
     *
     * @return mixed
     */
    private function id(EntityManagerInterface $entityManager, $entity)
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));

        try {
            $pk = $meta->getSingleIdentifierFieldName();
        } catch (ORMMappingException $e) {
            throw new MappingException(sprintf('Composite primary keys are not supported (%s).', \get_class($entity)));
        }

        if (isset($meta->fieldMappings[$pk])) {
            $type = Type::getType($meta->fieldMappings[$pk]['type']);

            return $this->value($entityManager, $type, $meta->getReflectionProperty($pk)->getValue($entity));
        }

        /**
         * Primary key is not part of fieldMapping.
         *
         * @see https://github.com/DamienHarper/auditor-bundle/issues/40
         * @see https://www.doctrine-project.org/projects/doctrine-orm/en/latest/tutorials/composite-primary-keys.html#identity-through-foreign-entities
         * We try to get it from associationMapping (will throw a MappingException if not available)
         */
        $targetEntity = $meta->getReflectionProperty($pk)->getValue($entity);

        $mapping = $meta->getAssociationMapping($pk);

        \assert(\is_string($mapping['targetEntity']));
        $meta = $entityManager->getClassMetadata($mapping['targetEntity']);
        $pk = $meta->getSingleIdentifierFieldName();
        $type = Type::getType($meta->fieldMappings[$pk]['type']);

        \assert(\is_object($targetEntity) || null === $targetEntity);

        return $this->value($entityManager, $type, $meta->getReflectionProperty($pk)->getValue($targetEntity));
    }

    /**
     * Type converts the input value and returns it.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function value(EntityManagerInterface $entityManager, Type $type, $value)
    {
        if (null === $value) {
            return;
        }

        $platform = $entityManager->getConnection()->getDatabasePlatform();

        switch ($type->getName()) {
            case DoctrineHelper::getDoctrineType('BIGINT'):
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
            case 'ulid':
                // Ramsey UUID / Symfony UID (UUID/ULID)
                $convertedValue = (string) $value;  // @phpstan-ignore-line

                break;
            case DoctrineHelper::getDoctrineType('BINARY'):
                if (\is_resource($value)) {
                    // let's replace resources with a "simple" representation: resourceType#resourceId
                    $convertedValue = get_resource_type($value).'#'.get_resource_id($value);
                } else {
                    $convertedValue = $type->convertToDatabaseValue($value, $platform);
                }

                break;
            default:
                $convertedValue = $type->convertToDatabaseValue($value, $platform);
        }

        return $convertedValue;
    }

    /**
     * Computes a usable diff.
     *
     * @param object $entity
     */
    private function diff(EntityManagerInterface $entityManager, $entity, array $changeset): array
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
     * @param null|object $entity
     */
    private function summarize(EntityManagerInterface $entityManager, $entity = null, array $extra = []): ?array
    {
        if (null === $entity) {
            return null;
        }

        $entityManager->getUnitOfWork()->initializeObject($entity); // ensure that proxies are initialized
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));

        $pkValue = $extra['id'] ?? $this->id($entityManager, $entity);
        $pkName = $meta->getSingleIdentifierFieldName();

        if (method_exists($entity, '__toString')) {
            $label = (string) $entity;
        } else {
            $label = DoctrineHelper::getRealClassName($entity).(null === $pkValue ? '' : '#'.$pkValue);
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
