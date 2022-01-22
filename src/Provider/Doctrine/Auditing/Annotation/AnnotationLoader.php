<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Annotation;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\Mapping\ClassMetadata;

class AnnotationLoader
{
    private ?AnnotationReader $reader = null;

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->reader = class_exists(AnnotationReader::class) ? new AnnotationReader() : null;
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
        $annotationProperty = null;
        $reflection = $metadata->getReflectionClass();

        // Check that we have an Entity annotation
        $attributes = \PHP_VERSION_ID >= 80000 && method_exists($reflection, 'getAttributes') ? $reflection->getAttributes(Entity::class) : null;
        if (\is_array($attributes) && \count($attributes) > 0) {
            $annotation = $attributes[0]->newInstance();
        } elseif (null !== $this->reader) {
            $annotation = $this->reader->getClassAnnotation($reflection, Entity::class);
        }

        if (null === $annotation) {
            return null;
        }

        // Check that we have an Auditable annotation
        $attributes = \PHP_VERSION_ID >= 80000 && method_exists($reflection, 'getAttributes') ? $reflection->getAttributes(Auditable::class) : null;
        if (\is_array($attributes) && \count($attributes) > 0) {
            $auditableAnnotation = $attributes[0]->newInstance();
        } elseif (null !== $this->reader) {
            $auditableAnnotation = $this->reader->getClassAnnotation($reflection, Auditable::class);
        }

        if (null === $auditableAnnotation) {
            return null;
        }

        // Check that we have a Security annotation
        $attributes = \PHP_VERSION_ID >= 80000 && method_exists($reflection, 'getAttributes') ? $reflection->getAttributes(Security::class) : null;
        if (\is_array($attributes) && \count($attributes) > 0) {
            $securityAnnotation = $attributes[0]->newInstance();
        } elseif (null !== $this->reader) {
            $securityAnnotation = $this->reader->getClassAnnotation($reflection, Security::class);
        }

        $roles = null === $securityAnnotation ? null : [Security::VIEW_SCOPE => $securityAnnotation->view];

        $config = [
            'ignored_columns' => [],
            'enabled' => $auditableAnnotation->enabled,
            'roles' => $roles,
        ];

        // Are there any Ignore annotations?
        foreach ($reflection->getProperties() as $property) {
            $attributes = \PHP_VERSION_ID >= 80000 && method_exists($property, 'getAttributes') ? $property->getAttributes(Ignore::class) : null;
            if (\is_array($attributes) && \count($attributes) > 0) {
                $annotationProperty = $attributes[0]->newInstance();
            } elseif (null !== $this->reader) {
                $annotationProperty = $this->reader->getPropertyAnnotation($property, Ignore::class);
            }

            if (null !== $annotationProperty) {
                // TODO: $property->getName() might not be the column name
                $config['ignored_columns'][] = $property->getName();
            }
        }

        return $config;
    }
}
