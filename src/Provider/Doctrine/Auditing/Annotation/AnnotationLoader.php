<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Annotation;

use DH\Auditor\Tests\Provider\Doctrine\Auditing\Annotation\AnnotationLoaderTest;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\Mapping\ClassMetadata;
use ReflectionClass;

/**
 * @see AnnotationLoaderTest
 */
final class AnnotationLoader
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

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
        $annotation = null;
        $auditableAnnotation = null;
        $securityAnnotation = null;
        $reflection = $metadata->getReflectionClass();

        // Check that we have an Entity annotation or attribute
        $attributes = $reflection->getAttributes(Entity::class);
        if (\is_array($attributes) && [] !== $attributes) {
            $annotation = $attributes[0]->newInstance();
        }

        if (!$annotation instanceof Entity) {
            return null;
        }

        // Check that we have an Auditable annotation or attribute
        $attributes = $reflection->getAttributes(Auditable::class);
        if (\is_array($attributes) && [] !== $attributes) {
            $auditableAnnotation = $attributes[0]->newInstance();
        }

        if (!$auditableAnnotation instanceof Auditable) {
            return null;
        }

        // Check that we have a Security annotation or attribute
        $attributes = $reflection->getAttributes(Security::class);
        if (\is_array($attributes) && [] !== $attributes) {
            $securityAnnotation = $attributes[0]->newInstance();
        }

        $roles = $securityAnnotation instanceof Security ? [Security::VIEW_SCOPE => $securityAnnotation->view] : null;

        // Are there any Ignore annotation or attribute?
        $ignoredColumns = $this->getAllProperties($reflection);

        return [
            'ignored_columns' => $ignoredColumns,
            'enabled' => $auditableAnnotation->enabled,
            'roles' => $roles,
        ];
    }

    private function getAllProperties(ReflectionClass $reflection): array
    {
        $annotationProperty = null;
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Ignore::class);
            if (\is_array($attributes) && [] !== $attributes) {
                $annotationProperty = $attributes[0]->newInstance();
            }

            if ($annotationProperty instanceof Ignore) {
                $properties[] = $property->getName();
            }
        }

        if (false !== $reflection->getParentClass()) {
            $properties = array_unique(array_merge($this->getAllProperties($reflection->getParentClass()), $properties));
        }

        return $properties;
    }
}
