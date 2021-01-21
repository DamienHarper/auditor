<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue18;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="data_object")
 */
class DataObject
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\Column(type="binary")
     */
    protected $data;

    /**
     * Get the value of id.
     *
     * @return mixed
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set the value of id.
     *
     * @return DataObject
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of data.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the value of data.
     *
     * @param mixed $data
     *
     * @return DataObject
     */
    public function setData($data): self
    {
        $this->data = $data;

        return $this;
    }
}
