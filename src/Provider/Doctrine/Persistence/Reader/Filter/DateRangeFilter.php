<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

use DH\Auditor\Exception\InvalidArgumentException;

final readonly class DateRangeFilter implements FilterInterface
{
    private ?\DateTimeInterface $minValue;

    private ?\DateTimeInterface $maxValue;

    public function __construct(private string $name, ?\DateTimeInterface $minValue, ?\DateTimeInterface $maxValue = null)
    {
        if ($minValue instanceof \DateTimeInterface && $maxValue instanceof \DateTimeInterface && $minValue > $maxValue) {
            throw new InvalidArgumentException('Max bound has to be later than min bound.');
        }

        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
    }

    public function getMinValue(): ?\DateTimeInterface
    {
        return $this->minValue;
    }

    public function getMaxValue(): ?\DateTimeInterface
    {
        return $this->maxValue;
    }

    /**
     * @return array{sql: string, params: array<string, string>}
     */
    public function getSQL(): array
    {
        $sqls = [];
        $params = [];

        if ($this->minValue instanceof \DateTimeInterface) {
            $sqls[] = \sprintf('%s >= :min_%s', $this->name, $this->name);
            $params['min_'.$this->name] = $this->minValue->format('Y-m-d H:i:s');
        }

        if ($this->maxValue instanceof \DateTimeInterface) {
            $sqls[] = \sprintf('%s <= :max_%s', $this->name, $this->name);
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
