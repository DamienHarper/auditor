<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

use DH\Auditor\Exception\InvalidArgumentException;

class RangeFilter implements FilterInterface
{
    protected string $name;

    /**
     * @var mixed
     */
    protected $minValue;

    /**
     * @var mixed
     */
    protected $maxValue;

    public function __construct(string $name, $minValue, $maxValue = null)
    {
        if (null === $minValue && null === $maxValue) {
            throw new InvalidArgumentException('You must provide at least one of the two range bounds.');
        }

        $this->name = $name;
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMinValue()
    {
        return $this->minValue;
    }

    public function getMaxValue()
    {
        return $this->maxValue;
    }

    public function getSQL(): array
    {
        $sqls = [];
        $params = [];

        if (null !== $this->minValue) {
            $sqls[] = sprintf('%s >= :min_%s', $this->name, $this->name);
            $params['min_'.$this->name] = $this->minValue;
        }

        if (null !== $this->maxValue) {
            $sqls[] = sprintf('%s <= :max_%s', $this->name, $this->name);
            $params['max_'.$this->name] = $this->maxValue;
        }

        return [
            'sql' => implode(' AND ', $sqls),
            'params' => $params,
        ];
    }
}
