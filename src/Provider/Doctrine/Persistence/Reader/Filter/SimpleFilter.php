<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

class SimpleFilter implements FilterInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array|string
     */
    protected $value;

    public function __construct(string $name, $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array|string
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getSQL(): array
    {
        if (\is_array($this->value) && 1 < \count($this->value)) {
            $data = [
                'sql' => sprintf('%s IN (:%s)', $this->name, $this->name),
                'params' => [$this->name => $this->value],
            ];
        } else {
            $data = [
                'sql' => sprintf('%s = :%s', $this->name, $this->name),
                'params' => [$this->name => (\is_array($this->value) ? $this->value[0] : $this->value)],
            ];
        }

        return $data;
    }
}
