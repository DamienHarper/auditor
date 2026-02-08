<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

use DH\Auditor\Exception\InvalidArgumentException;

final readonly class RangeFilter implements FilterInterface
{
    private mixed $minValue;

    private mixed $maxValue;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(private string $name, mixed $minValue, mixed $maxValue = null)
    {
        if (null === $minValue && null === $maxValue) {
            throw new InvalidArgumentException('You must provide at least one of the two range bounds.');
        }

        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMinValue(): mixed
    {
        return $this->minValue;
    }

    public function getMaxValue(): mixed
    {
        return $this->maxValue;
    }

    /**
     * @return array{sql: string, params: array<string, mixed>}
     */
    public function getSQL(): array
    {
        $sqls = [];
        $params = [];

        if (null !== $this->minValue) {
            $sqls[] = \sprintf('%s >= :min_%s', $this->name, $this->name);
            $params['min_'.$this->name] = $this->minValue;
        }

        if (null !== $this->maxValue) {
            $sqls[] = \sprintf('%s <= :max_%s', $this->name, $this->name);
            $params['max_'.$this->name] = $this->maxValue;
        }

        return [
            'sql' => implode(' AND ', $sqls),
            'params' => $params,
        ];
    }
}
