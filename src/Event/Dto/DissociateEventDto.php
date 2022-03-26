<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

final class DissociateEventDto extends AbstractAssociationEventDto
{
    private $id;

    public function __construct(object $source, object $target, $id, array $mapping)
    {
        \assert(!empty($id));

        parent::__construct($source, $target, $mapping);

        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }
}
