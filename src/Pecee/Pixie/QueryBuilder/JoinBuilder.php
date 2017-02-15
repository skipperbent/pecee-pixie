<?php
namespace Pecee\Pixie\QueryBuilder;

class JoinBuilder extends QueryBuilderHandler
{
    /**
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     *
     * @return static
     */
    public function on($key, $operator, $value)
    {
        return $this->joinHandler($key, $operator, $value, 'AND');
    }

    /**
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     *
     * @return static
     */
    public function orOn($key, $operator, $value)
    {
        return $this->joinHandler($key, $operator, $value, 'OR');
    }

    /**
     * @param string$key
     * @param string|mixed|null $operator
     * @param string|mixed|null $value
     * @param string $joiner
     *
     * @return static
     */
    protected function joinHandler($key, $operator = null, $value = null, $joiner = 'AND')
    {
        $key = $this->addTablePrefix($key);
        $value = $this->addTablePrefix($value);
        $this->statements['criteria'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }
}
