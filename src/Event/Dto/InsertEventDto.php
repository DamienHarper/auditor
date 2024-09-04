<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

final class InsertEventDto extends AbstractEventDto
{
    public function __construct(object $source, private readonly array $changeset)
    {
        parent::__construct($source);
    }

    public function getChangeset(): array
    {
        return $this->changeset;
    }
}
