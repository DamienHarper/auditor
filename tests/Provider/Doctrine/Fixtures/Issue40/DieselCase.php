<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue40;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\HasLifecycleCallbacks
 *
 * @ORM\Table(name="case_diesel")
 */
#[ORM\Entity, ORM\HasLifecycleCallbacks, ORM\Table(name: 'case_diesel')]
class DieselCase
{
    /**
     * @ORM\Id
     *
     * @ORM\ManyToOne(targetEntity="CoreCase", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="core_case", referencedColumnName="id")
     */
    #[ORM\Id, ORM\ManyToOne(targetEntity: 'CoreCase', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'core_case', referencedColumnName: 'id')]
    public $coreCase;

    /**
     * @ORM\Column(type="string", length=50)
     */
    #[ORM\Column(type: 'string', length: 50)]
    protected $name;

    /**
     * Get the value of name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name.
     *
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }
}
