<?php

namespace Pecee\Pixie\QueryBuilder;

/**
 * Class NestedCriteria
 *
 * @package Pecee\Pixie\QueryBuilder
 */
class NestedCriteria extends QueryBuilderHandler
{
    /**
     * @param string|Raw|\Closure $key
     * @param string|null $operator
     * @param string|array|Raw|\Closure|null $value
     * @param string $joiner
     *
     * @return static
     */
    protected function whereHandler($key, string $operator = null, $value = null, $joiner = 'AND'): QueryBuilderHandler
    {
        $key = $this->addTablePrefix($key);
        $this->statements['criteria'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }
}
