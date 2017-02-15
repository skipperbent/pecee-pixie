<?php
namespace Pecee\Pixie\QueryBuilder;

class NestedCriteria extends QueryBuilderHandler
{
    /**
     * @param string $key
     * @param string|mixed|null $operator
     * @param string|mixed|null $value
     * @param string $joiner
     * @return static
     */
    protected function whereHandler($key, $operator = null, $value = null, $joiner = 'AND')
    {
        $key = $this->addTablePrefix($key);
        $this->statements['criteria'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }
}
