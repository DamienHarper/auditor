<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

abstract class AbstractEventDto
{
    public function __construct(public readonly object $source) {}
}
