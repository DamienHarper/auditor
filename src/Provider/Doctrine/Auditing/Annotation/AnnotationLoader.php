<?php

namespace DH\Auditor\Provider\Doctrine\Auditing\Annotation;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\Mapping\ClassMetadata;

class AnnotationLoader
{
    /**
     * @var null|AnnotationReader
     */
    private $reader;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

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
        if (\PHP_VERSION_ID >= 80000 && $attributes = $reflection->getAttributes(Entity::class)) {
            $annotation = $attributes[0]->newInstance();
        } elseif (null !== $this->reader) {
            $annotation = $this->reader->getClassAnnotation($reflection, Entity::class);
        }

        if (null === $annotation) {
            return null;
        }

        // Check that we have an Auditable annotation
        if (\PHP_VERSION_ID >= 80000 && $attributes = $reflection->getAttributes(Auditable::class)) {
            /** @var ?Auditable $auditableAnnotation */
            $auditableAnnotation = $attributes[0]->newInstance();
        } elseif (null !== $this->reader) {
            /** @var ?Auditable $auditableAnnotation */
            $auditableAnnotation = $this->reader->getClassAnnotation($reflection, Auditable::class);
        }

        if (null === $auditableAnnotation) {
            return null;
        }

        // Check that we have an Security annotation
        if (\PHP_VERSION_ID >= 80000 && $attributes = $reflection->getAttributes(Security::class)) {
            /** @var ?Security $securityAnnotation */
            $securityAnnotation = $attributes[0]->newInstance();
        } elseif (null !== $this->reader) {
            /** @var ?Security $securityAnnotation */
            $securityAnnotation = $this->reader->getClassAnnotation($reflection, Security::class);
        }

        if (null === $securityAnnotation) {
            $roles = null;
        } else {
            $roles = [
                Security::VIEW_SCOPE => $securityAnnotation->view,
            ];
        }

        $config = [
            'ignored_columns' => [],
            'enabled' => $auditableAnnotation->enabled,
            'roles' => $roles,
        ];

        // Are there any Ignore annotations?
        foreach ($reflection->getProperties() as $property) {
            if (\PHP_VERSION_ID >= 80000 && $attributes = $property->getAttributes(Ignore::class)) {
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
