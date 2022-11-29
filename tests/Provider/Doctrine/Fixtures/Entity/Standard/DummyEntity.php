<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dummy_entity')]
class DummyEntity
{
    #[ORM\Column(type: Types::STRING, length: 50)]
    protected ?string $label = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $int_value = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    protected ?string $decimal_value = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => '0'])]
    protected ?bool $bool_value = null;

    #[ORM\Column(type: Types::ARRAY)]
    protected ?array $php_array = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    protected $json_array;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    protected $simple_array;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(mixed $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getPhpArray(): ?array
    {
        return $this->php_array;
    }

    public function setPhpArray(array $php_array): self
    {
        $this->php_array = $php_array;

        return $this;
    }

    public function getJsonArray()
    {
        return $this->json_array;
    }

    public function setJsonArray($json_array): self
    {
        $this->json_array = $json_array;

        return $this;
    }

    public function getSimpleArray()
    {
        return $this->simple_array;
    }

    public function setSimpleArray($simple_array): self
    {
        $this->simple_array = $simple_array;

        return $this;
    }

    public function getIntValue(): ?int
    {
        return $this->int_value;
    }

    public function setIntValue(?int $int_value): self
    {
        $this->int_value = $int_value;

        return $this;
    }

    public function getDecimalValue(): ?string
    {
        return $this->decimal_value;
    }

    public function setDecimalValue(?string $decimal_value): self
    {
        $this->decimal_value = $decimal_value;

        return $this;
    }

    public function getBoolValue(): ?bool
    {
        return $this->bool_value;
    }

    public function setBoolValue(?bool $bool_value): self
    {
        $this->bool_value = $bool_value;

        return $this;
    }
}
