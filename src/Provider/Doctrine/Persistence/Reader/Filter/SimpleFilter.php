<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

final class SimpleFilter implements FilterInterface
{
    private string $name;

    private mixed $value;

    public function __construct(string $name, mixed $value)
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
