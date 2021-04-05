<?php

namespace Pecee\Pixie\QueryBuilder;

use Pecee\Pixie\Connection;

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
     * @var Connection
     */
    protected $connection;

    /**
     * QueryObject constructor.
     *
     * @param string $sql
     * @param array $bindings
     * @param Connection $connection
     */
    public function __construct(string $sql, array $bindings, Connection $connection)
    {
        $this->sql = $sql;
        $this->bindings = $bindings;
        $this->connection = $connection;
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
     * @param string $query The sql query with parameter placeholders
     * @param array $params The array of substitution parameters
     *
     * @return string The interpolated query
     */
    protected function interpolateQuery($query, $params): string
    {
        $keys = [];
        $values = $params;

        // build a regular expression for each parameter
        foreach ($params as $key => $value) {
            $keys[] = '/' . (\is_string($key) ? ':' . $key : '[?]') . '/';

            if($value instanceof Raw) {
                continue;
            }

            // Try to parse object-types
            if(\is_object($value) === true) {
                $value = (string)$value;
            }

            if (\is_string($value) === true) {
                $values[$key] = $this->connection->getPdoInstance()->quote($value);
                continue;
            }

            if (\is_array($value) === true) {
                $values[$key] = $this->connection->getPdoInstance()->quote(implode(',', $value));
                continue;
            }

            if ($value === null) {
                $values[$key] = 'NULL';
                continue;
            }
        }

        return preg_replace($keys, $values, $query, 1, $count);
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

}