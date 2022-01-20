<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

use DateTimeInterface;
use DH\Auditor\Exception\InvalidArgumentException;

class DateRangeFilter implements FilterInterface
{
    protected string $name;

    protected ?DateTimeInterface $minValue;

    protected ?DateTimeInterface $maxValue;

    public function __construct(string $name, ?DateTimeInterface $minValue, ?DateTimeInterface $maxValue = null)
    {
        if (null !== $minValue && null !== $maxValue && $minValue > $maxValue) {
            throw new InvalidArgumentException('Max bound has to be later than min bound.');
        }

        $this->name = $name;
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
    }

    public function getMinValue(): ?DateTimeInterface
    {
        return $this->minValue;
    }

    public function getMaxValue(): ?DateTimeInterface
    {
        return $this->maxValue;
    }

    public function getSQL(): array
    {
        $sqls = [];
        $params = [];

        if (null !== $this->minValue) {
            $sqls[] = sprintf('%s >= :min_%s', $this->name, $this->name);
            $params['min_'.$this->name] = $this->minValue->format('Y-m-d H:i:s');
        }

        if (null !== $this->maxValue) {
            $sqls[] = sprintf('%s <= :max_%s', $this->name, $this->name);
            $params['max_'.$this->name] = $this->maxValue->format('Y-m-d H:i:s');
        }

        return [
            'sql' => implode(' AND ', $sqls),
            'params' => $params,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }
}
