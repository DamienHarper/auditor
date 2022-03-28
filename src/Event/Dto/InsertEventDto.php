<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

final class InsertEventDto extends AbstractEventDto
{
    private array $changeset;

    public function __construct(object $source, array $changeset)
    {
        parent::__construct($source);

        $this->changeset = $changeset;
    }

    public function getChangeset(): array
    {
        return $this->changeset;
    }
}
