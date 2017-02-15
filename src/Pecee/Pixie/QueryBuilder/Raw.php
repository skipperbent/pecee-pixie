<?php
namespace Pecee\Pixie\QueryBuilder;

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
    public function __construct($value, $bindings = [])
    {
        $this->value = (string)$value;
        $this->bindings = (array)$bindings;
    }

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
