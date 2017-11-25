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
     * @var array
     */
    protected $bindings;

    /**
     * Raw constructor.
     * @param string $value
     * @param array|string $bindings
     */
    public function __construct($value, array $bindings = [])
    {
        $this->value = (string)$value;
        $this->bindings = (array)$bindings;
    }

    /**
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->value;
    }
}
