<?php

namespace DH\Auditor\Provider\Doctrine;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\Doctrine\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Helper\DoctrineHelper;

class DoctrineProvider extends AbstractProvider
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var AnnotationLoader
     */
    private $annotationLoader;

    public function __construct(Configuration $configuration, AnnotationLoader $annotationLoader)
    {
        $this->configuration = $configuration;
        $this->annotationLoader = $annotationLoader;

        $this->configuration->setEntities(array_merge(
            $this->configuration->getEntities(),
            $this->annotationLoader->load()
        ));
    }

    public function persist(LifecycleEvent $event): void
    {
        // TODO: Implement persist() method.
    }

    public function getAnnotationLoader(): AnnotationLoader
    {
        return $this->annotationLoader;
    }

    /**
     * Returns true if $entity is auditable.
     *
     * @param object|string $entity
     */
    public function isAuditable($entity): bool
    {
        $class = DoctrineHelper::getRealClassName($entity);

        // is $entity part of audited entities?
        if (!\array_key_exists($class, $this->configuration->getEntities())) {
            // no => $entity is not audited
            return false;
        }

        return true;
    }

    /**
     * Returns true if $entity is audited.
     *
     * @param object|string $entity
     */
    public function isAudited($entity): bool
    {
        if (!$this->auditor->getConfiguration()->isEnabled()) {
            return false;
        }

        $class = DoctrineHelper::getRealClassName($entity);

        // is $entity part of audited entities?
        if (!\array_key_exists($class, $this->configuration->getEntities())) {
            // no => $entity is not audited
            return false;
        }

        $entityOptions = $this->configuration->getEntities()[$class];

        if (null === $entityOptions) {
            // no option defined => $entity is audited
            return true;
        }

        if (isset($entityOptions['enabled'])) {
            return (bool) $entityOptions['enabled'];
        }

        return true;
    }

    /**
     * Returns true if $field is audited.
     *
     * @param object|string $entity
     */
    public function isAuditedField($entity, string $field): bool
    {
        // is $field is part of globally ignored columns?
        if (\in_array($field, $this->configuration->getIgnoredColumns(), true)) {
            // yes => $field is not audited
            return false;
        }

        // is $entity audited?
        if (!$this->isAudited($entity)) {
            // no => $field is not audited
            return false;
        }

        $class = DoctrineHelper::getRealClassName($entity);
        $entityOptions = $this->configuration->getEntities()[$class];

        if (null === $entityOptions) {
            // no option defined => $field is audited
            return true;
        }

        // are columns excluded and is field part of them?
        if (isset($entityOptions['ignored_columns']) &&
            \in_array($field, $entityOptions['ignored_columns'], true)) {
            // yes => $field is not audited
            return false;
        }

        return true;
    }
}
