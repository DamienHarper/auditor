<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

final class RemoveEventDto extends AbstractEventDto
{
    public function __construct(object $source, private readonly mixed $id)
    {
        parent::__construct($source);
    }

    public function getId(): mixed
    {
        return $this->id;
    }
}
