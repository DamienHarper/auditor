<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

final class RemoveEventDto extends AbstractEventDto
{
    private mixed $id;

    public function __construct(object $source, mixed $id)
    {
        parent::__construct($source);
        $this->id = $id;
    }

    public function getId(): mixed
    {
        return $this->id;
    }
}
