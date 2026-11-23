<?php

declare(strict_types=1);

namespace DH\Auditor\Contract;

/**
 * Turns a field of an audited entity into a label meant for end users, such as "Price" for App\Entity\Property::price.
 */
interface FieldLabelResolverInterface
{
    /**
     * @param string $entity FQCN of the audited entity
     * @param string $field  name of the field, or of the association for collection changes
     */
    public function resolve(string $entity, string $field): string;
}
