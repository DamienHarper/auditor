<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue112;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="author")
 */
#[ORM\Entity]
#[ORM\Table(name: 'author')]
class DummyEntity
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer", options={"unsigned": true})
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $primaryKey;

    public function getPrimaryKey(): int
    {
        return $this->primaryKey;
    }

    public function setPrimaryKey(int $primaryKey): void
    {
        $this->primaryKey = $primaryKey;
    }
}
