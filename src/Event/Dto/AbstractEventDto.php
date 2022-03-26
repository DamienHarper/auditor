<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

abstract class AbstractEventDto
{
    private object $source;

    public function __construct(object $source)
    {
        $this->source = $source;
    }

    public function getSource(): object
    {
        return $this->source;
    }
}
