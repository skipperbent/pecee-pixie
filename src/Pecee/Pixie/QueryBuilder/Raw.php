<?php

namespace Pecee\Pixie\QueryBuilder;

/**
 * Class Raw
 */
class Raw
{
    /**
     * @var string
     */
    protected $value;

    /**
     * @var array
     */
    protected $bindings;

    /**
     * Raw constructor.
     *
     * @param string       $value
     * @param array|string $bindings
     */
    public function __construct(string $value, array $bindings = [])
    {
        $this->value    = $value;
        $this->bindings = $bindings;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->value;
    }

    /**
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
