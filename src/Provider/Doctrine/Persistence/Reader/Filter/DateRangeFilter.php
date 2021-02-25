<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

use DateTime;
use DH\Auditor\Exception\InvalidArgumentException;

class DateRangeFilter extends RangeFilter
{
    public function __construct(string $name, ?DateTime $minValue, ?DateTime $maxValue = null)
    {
        parent::__construct($name, $minValue, $maxValue);

        if (null !== $minValue && null !== $maxValue && $minValue > $maxValue) {
            throw new InvalidArgumentException('Max bound has to be later than min bound.');
        }

//        $this->filters[$name][] = [
//            null === $minValue ? null : $minValue->format('Y-m-d H:i:s'),
//            null === $maxValue ? null : $maxValue->format('Y-m-d H:i:s'),
//        ];
    }

    /**
     * @return DateTime
     */
    public function getMinValue(): ?DateTime
    {
        return $this->minValue;
    }

    /**
     * @return DateTime
     */
    public function getMaxValue(): ?DateTime
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
}
