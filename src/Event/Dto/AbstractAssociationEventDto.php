<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

abstract class AbstractAssociationEventDto extends AbstractEventDto
{
    private object $target;

    private array $mapping;

    public function __construct(object $source, object $target, array $mapping)
    {
        parent::__construct($source);

        $this->target = $target;
        $this->mapping = $mapping;
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
