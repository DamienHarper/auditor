<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

final class RemoveEventDto extends AbstractEventDto
{
    private $id;

    public function __construct(object $source, $id)
    {
        parent::__construct($source);
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }
}
