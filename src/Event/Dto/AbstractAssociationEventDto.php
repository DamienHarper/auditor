<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

abstract class AbstractAssociationEventDto extends AbstractEventDto
{
    public function __construct(object $source, private readonly object $target, private readonly array $mapping)
    {
        parent::__construct($source);
    }

    public function getTarget(): object
    {
        return $this->target;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }
}
