<?php

declare(strict_types=1);

namespace DH\Auditor\Contract;

/**
 * Builds the label of an entity, written at write time in the "source" and "target" blocks of the diffs.
 *
 * Return null to fall back to the default label: the string representation of the entity when it has one,
 * otherwise "Class#id". Like DiffLabelResolverInterface, a resolver runs during the flush: it MUST NOT flush the
 * entity manager.
 */
interface EntityLabelResolverInterface
{
    public function __invoke(object $entity): ?string;
}
