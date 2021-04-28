<?php

namespace Pecee\Pixie\QueryBuilder;

/**
 * Class Raw
 *
 * @package Pecee\Pixie\QueryBuilder
 */
class Raw
{

    /**
     * @var string
     */
    protected $value;

    /**
     * @var mixed[]
     */
    protected $bindings;

    /**
     * Raw constructor.
     *
     * @param string $value
     * @param array|string $bindings
     */
    public function __construct(string $value, $bindings = [])
    {
        $this->value = $value;
        $this->bindings = (array)$bindings;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @return mixed[]
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
