<?php

declare(strict_types=1);

namespace DH\Auditor\Event\Dto;

final class RemoveEventDto extends AbstractEventDto
{
    /**
     * @var mixed
     */
    private $id;

    /**
     * @param mixed $id
     */
    public function __construct(object $source, $id)
    {
        parent::__construct($source);
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}
