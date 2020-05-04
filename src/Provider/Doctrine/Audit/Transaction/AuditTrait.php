<?php

namespace DH\Auditor\Provider\Doctrine\Audit\Transaction;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\User\UserInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;

trait AuditTrait
{
    /**
     * Returns the primary key value of an entity.
     *
     * @param mixed $entity
     */
    private function id(EntityManagerInterface $entityManager, $entity)
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $pk = $meta->getSingleIdentifierFieldName();

        if (isset($meta->fieldMappings[$pk])) {
            $type = Type::getType($meta->fieldMappings[$pk]['type']);

            return $this->value($entityManager, $type, $meta->getReflectionProperty($pk)->getValue($entity));
        }

        /**
         * Primary key is not part of fieldMapping.
         *
         * @see https://github.com/DamienHarper/Auditor\Provider\Doctrine/issues/40
         * @see https://www.doctrine-project.org/projects/doctrine-orm/en/latest/tutorials/composite-primary-keys.html#identity-through-foreign-entities
         * We try to get it from associationMapping (will throw a MappingException if not available)
         */
        $targetEntity = $meta->getReflectionProperty($pk)->getValue($entity);

        $mapping = $meta->getAssociationMapping($pk);

        $meta = $entityManager->getClassMetadata($mapping['targetEntity']);
        $pk = $meta->getSingleIdentifierFieldName();
        $type = Type::getType($meta->fieldMappings[$pk]['type']);

        return $this->value($entityManager, $type, $meta->getReflectionProperty($pk)->getValue($targetEntity));
    }

    /**
     * Type converts the input value and returns it.
     *
     * @param mixed $value
     */
    private function value(EntityManagerInterface $entityManager, Type $type, $value)
    {
        if (null === $value) {
            return;
        }

        $platform = $entityManager->getConnection()->getDatabasePlatform();

        switch ($type->getName()) {
            case DoctrineHelper::getDoctrineType('BIGINT'):
                $convertedValue = (string) $value;

                break;
            case DoctrineHelper::getDoctrineType('INTEGER'):
            case DoctrineHelper::getDoctrineType('SMALLINT'):
                $convertedValue = (int) $value;

                break;
            case DoctrineHelper::getDoctrineType('DECIMAL'):
            case DoctrineHelper::getDoctrineType('FLOAT'):
            case DoctrineHelper::getDoctrineType('BOOLEAN'):
                $convertedValue = $type->convertToPHPValue($value, $platform);

                break;
            default:
                $convertedValue = $type->convertToDatabaseValue($value, $platform);
        }

        return $convertedValue;
    }

    /**
     * Computes a usable diff.
     *
     * @param mixed $entity
     */
    private function diff(EntityManagerInterface $entityManager, $entity, array $changeset): array
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $diff = [];

        foreach ($changeset as $fieldName => [$old, $new]) {
            $o = null;
            $n = null;

            if (
                !isset($meta->embeddedClasses[$fieldName]) &&
                $meta->hasField($fieldName) &&
                $this->provider->isAuditedField($entity, $fieldName)
            ) {
                $mapping = $meta->fieldMappings[$fieldName];
                $type = Type::getType($mapping['type']);
                $o = $this->value($entityManager, $type, $old);
                $n = $this->value($entityManager, $type, $new);
            } elseif (
                $meta->hasAssociation($fieldName) &&
                $meta->isSingleValuedAssociation($fieldName) &&
                $this->provider->isAuditedField($entity, $fieldName)
            ) {
                $o = $this->summarize($entityManager, $old);
                $n = $this->summarize($entityManager, $new);
            }

            if ($o !== $n) {
                $diff[$fieldName] = [
                    'old' => $o,
                    'new' => $n,
                ];
            }
        }
        ksort($diff);

        return $diff;
    }

    /**
     * Returns an array describing an entity.
     *
     * @param null|mixed $entity
     * @param null|mixed $id
     */
    private function summarize(EntityManagerInterface $entityManager, $entity = null, $id = null): ?array
    {
        if (null === $entity) {
            return null;
        }

        $entityManager->getUnitOfWork()->initializeObject($entity); // ensure that proxies are initialized
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $pkName = $meta->getSingleIdentifierFieldName();
        $pkValue = $id ?? $this->id($entityManager, $entity);
        // An added guard for proxies that fail to initialize.
        if (null === $pkValue) {
            return null;
        }

        if (method_exists($entity, '__toString')) {
            $label = (string) $entity;
        } else {
            $label = DoctrineHelper::getRealClassName($entity).'#'.$pkValue;
        }

        return [
            'label' => $label,
            'class' => $meta->name,
            'table' => $meta->getTableName(),
            $pkName => $pkValue,
        ];
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

//        $request = $this->configuration->getRequestStack()->getCurrentRequest();
//        if (null !== $request) {
//            $client_ip = $request->getClientIp();
//            $user_firewall = null === $this->configuration->getFirewallMap()->getFirewallConfig($request) ? null : $this->configuration->getFirewallMap()->getFirewallConfig($request)->getName();
//        }
//
//        $user = null === $this->configuration->getUserProvider() ? null : $this->configuration->getUserProvider()->getUser();
//        if ($user instanceof UserInterface) {
//            $user_id = $user->getId();
//            $username = $user->getUsername();
//            $user_fqdn = DoctrineHelper::getRealClassName($user);
//        }

        return [
            'user_id' => $user_id,
            'username' => $username,
            'client_ip' => $client_ip,
            'user_fqdn' => $user_fqdn,
            'user_firewall' => $user_firewall,
        ];
    }
}
