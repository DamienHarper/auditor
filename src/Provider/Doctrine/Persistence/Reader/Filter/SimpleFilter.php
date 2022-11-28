<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

class SimpleFilter implements FilterInterface
{
    protected string $name;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @param array|string $value
     */
    public function __construct(string $name, $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getSQL(): array
    {
        if (\is_array($this->value) && 1 < \count($this->value)) {
            return [
                'sql' => sprintf('%s IN (:%s)', $this->name, $this->name),
                'params' => [$this->name => $this->value],
            ];
        }

        return [
            'sql' => sprintf('%s = :%s', $this->name, $this->name),
            'params' => [$this->name => (\is_array($this->value) ? $this->value[0] : $this->value)],
        ];
    }
}
