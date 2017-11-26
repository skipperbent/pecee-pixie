<?php

namespace Pecee\Pixie\QueryBuilder;

/**
 * Class QueryObject
 *
 * @package Pecee\Pixie\QueryBuilder
 */
class QueryObject
{

    /**
     * @var string
     */
    protected $sql;

    /**
     * @var array
     */
    protected $bindings = [];

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * QueryObject constructor.
     *
     * @param string $sql
     * @param array  $bindings
     * @param \PDO   $pdo
     */
    public function __construct(string $sql, array $bindings, \PDO $pdo)
    {
        $this->sql      = $sql;
        $this->bindings = $bindings;
        $this->pdo      = $pdo;
    }

    /**
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get the raw/bound sql
     *
     * @return string
     */
    public function getRawSql(): string
    {
        return $this->interpolateQuery($this->sql, $this->bindings);
    }

    /**
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Replaces any parameter placeholders in a query with the value of that
     * parameter. Useful for debugging. Assumes anonymous parameters from
     * $params are are in the same order as specified in $query
     *
     * Reference: http://stackoverflow.com/a/1376838/656489
     *
     * @param string $query  The sql query with parameter placeholders
     * @param array  $params The array of substitution parameters
     *
     * @return string The interpolated query
     */
    protected function interpolateQuery($query, $params)
    {
        $keys   = [];
        $values = $params;

        // build a regular expression for each parameter
        foreach ($params as $key => $value) {
            $keys[] = '/' . (is_string($key) ? ':' . $key : '[?]') . '/';

            if (is_string($value) === true) {
                $values[$key] = $this->pdo->quote($value);
                continue;
            }

            if (is_array($value) === true) {
                $values[$key] = $this->pdo->quote(implode(',', $value));
                continue;
            }

            if ($value === null) {
                $values[$key] = 'NULL';
                continue;
            }
        }

        return preg_replace($keys, $values, $query, 1, $count);
    }
}
