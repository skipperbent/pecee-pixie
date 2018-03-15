<?php

namespace Pecee\Pixie\QueryBuilder;

/**
 * Class JoinBuilder
 *
 * @package Pecee\Pixie\QueryBuilder
 */
class JoinBuilder extends QueryBuilderHandler
{
    /**
     * Add join
     *
     * @param string|Raw|\Closure $key
     * @param string|Raw|\Closure $operator
     * @param string|Raw|\Closure $value
     * @param string $joiner
     *
     * @return static
     */
    public function on($key, $operator, $value, $joiner = 'AND'): self
    {
        $this->statements['criteria'][] = [
            'key'      => $this->addTablePrefix($key),
            'operator' => $operator,
            'value'    => $this->addTablePrefix($value),
            'joiner'   => $joiner,
        ];

        return $this;
    }

    /**
     * Add join with USING syntax
     *
     * @param string|Raw|\Closure $table
     * @param array $columns
     * @param string $joiner
     * @return static
     */
    public function using($table, array $columns, $joiner = 'AND'): self
    {
        $this->statements['criteria'][] = [
            'key'     => $this->addTablePrefix($table),
            'columns' => $this->addTablePrefix($columns),
            'joiner'  => $joiner,
        ];

        return $this;
    }

    /**
     * Add OR join with USING syntax
     *
     * @param string|Raw|\Closure $table
     * @param array $columns
     * @return static
     */
    public function orUsing($table, array $columns): self
    {
        return $this->using(
            $this->addTablePrefix($table),
            $this->addTablePrefix($columns),
            'OR'
        );
    }

    /**
     * Add OR ON join
     *
     * @param string|Raw|\Closure $key
     * @param string|Raw|\Closure $operator
     * @param string|Raw|\Closure $value
     *
     * @return static
     */
    public function orOn($key, $operator, $value): self
    {
        return $this->on($key, $operator, $value, 'OR');
    }

}