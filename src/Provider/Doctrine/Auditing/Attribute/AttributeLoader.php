<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Attribute;

use DH\Auditor\Tests\Provider\Doctrine\Auditing\Attribute\AttributeLoaderTest;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\Mapping\ClassMetadata;

/**
 * @see AttributeLoaderTest
 */
final readonly class AttributeLoader
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function load(): array
    {
        $configuration = [];

        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        foreach ($metadatas as $metadata) {
            $config = $this->getEntityConfiguration($metadata);
            if (null !== $config) {
                $configuration[$metadata->getName()] = $config;
            }
        }

        return $configuration;
    }

    private function getEntityConfiguration(ClassMetadata $metadata): ?array
    {
        $entityAttribute = null;
        $auditableAttribute = null;
        $securityAttribute = null;
        $reflection = $metadata->getReflectionClass();

        // Check that we have an Entity attribute
        $attributes = $reflection->getAttributes(Entity::class);
        if ([] !== $attributes) {
            $entityAttribute = $attributes[0]->newInstance();
        }

        if (!$entityAttribute instanceof Entity) {
            return null;
        }

        // Check that we have an Auditable attribute
        $attributes = $reflection->getAttributes(Auditable::class);
        if ([] !== $attributes) {
            $auditableAttribute = $attributes[0]->newInstance();
        }

        if (!$auditableAttribute instanceof Auditable) {
            return null;
        }

        // Check that we have a Security attribute
        $attributes = $reflection->getAttributes(Security::class);
        if ([] !== $attributes) {
            $securityAttribute = $attributes[0]->newInstance();
        }

        $roles = $securityAttribute instanceof Security ? [Security::VIEW_SCOPE => $securityAttribute->view] : null;

        // Are there any Ignore attributes?
        $ignoredColumns = $this->getAllProperties($reflection);

        return [
            'ignored_columns' => $ignoredColumns,
            'enabled' => $auditableAttribute->enabled,
            'roles' => $roles,
        ];
    }

    private function getAllProperties(\ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $ignoreAttribute = null;
            $attributes = $property->getAttributes(Ignore::class);
            if (\is_array($attributes) && [] !== $attributes) {
                $ignoreAttribute = $attributes[0]->newInstance();
            }

            if ($ignoreAttribute instanceof Ignore) {
                $properties[] = $property->getName();
            }
        }

        if (false !== $reflection->getParentClass()) {
            return array_unique(array_merge($this->getAllProperties($reflection->getParentClass()), $properties));
        }

        return $properties;
    }
}
