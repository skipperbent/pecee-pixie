<?php

namespace Pecee\Pixie\QueryBuilder\Adapters;

use Pecee\Pixie\Connection;
use Pecee\Pixie\Exception;
use Pecee\Pixie\QueryBuilder\NestedCriteria;
use Pecee\Pixie\QueryBuilder\Raw;

/**
 * Class BaseAdapter
 *
 * @package Pecee\Pixie\QueryBuilder\Adapters
 */
abstract class BaseAdapter
{
    /**
     * @var string
     */
    const SANITIZER = '`';

    /**
     * @var \Pecee\Pixie\Connection
     */
    protected $connection;

    /**
     * @var \Viocon\Container
     */
    protected $container;

    /**
     * BaseAdapter constructor.
     *
     * @param \Pecee\Pixie\Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->container = $this->connection->getContainer();
    }

    /**
     * Array concatenating method, like implode.
     * But it does wrap sanitizer and trims last glue
     *
     * @param array $pieces
     * @param string $glue
     * @param bool $wrapSanitizer
     *
     * @return string
     */
    protected function arrayStr(array $pieces, $glue = ',', $wrapSanitizer = true): string
    {
        $str = '';
        foreach ($pieces as $key => $piece) {
            if ($wrapSanitizer === true) {
                $piece = $this->wrapSanitizer($piece);
            }

            if (\is_int($key) === false) {
                $piece = ($wrapSanitizer ? $this->wrapSanitizer($key) : $key) . ' AS ' . $piece;
            }

            $str .= $piece . $glue;
        }

        return trim($str, $glue);
    }

    /**
     * Build generic criteria string and bindings from statements, like "a = b and c = ?"
     *
     * @param array $statements
     * @param bool $bindValues
     *
     * @throws Exception
     * @return array
     */
    protected function buildCriteria(array $statements, $bindValues = true): array
    {
        $criteria = '';
        $bindings = [[]];

        foreach ($statements as $statement) {

            $key = $this->wrapSanitizer($statement['key']);
            $value = $statement['value'];

            if ($value === null && $key instanceof \Closure) {

                /**
                 * We have a closure, a nested criteria
                 * Build a new NestedCriteria class, keep it by reference so any changes made in the closure should reflect here
                 */

                /* @var $nestedCriteria NestedCriteria */
                $nestedCriteria = $this->container->build(
                    NestedCriteria::class,
                    [$this->connection]
                );

                // Call the closure with our new nestedCriteria object
                $key($nestedCriteria);

                // Get the criteria only query from the nestedCriteria object
                $queryObject = $nestedCriteria->getQuery('criteriaOnly', true);

                // Merge the bindings we get from nestedCriteria object
                $bindings[] = $queryObject->getBindings();

                // Append the sql we get from the nestedCriteria object
                $criteria .= $statement['joiner'] . ' (' . $queryObject->getSql() . ') ';

                continue;
            }

            if (\is_array($value) === true) {

                // Where in or between like query
                $criteria .= $statement['joiner'] . ' ' . $key . ' ' . $statement['operator'];

                if ($statement['operator'] === 'BETWEEN') {
                    $bindings[] = [$statement['value']];
                    $criteria .= ' ? AND ? ';
                } else {
                    $valuePlaceholder = '';
                    foreach ((array)$statement['value'] as $subValue) {
                        $valuePlaceholder .= '?, ';
                        $bindings[] = [$subValue];
                    }

                    $valuePlaceholder = trim($valuePlaceholder, ', ');
                    $criteria .= ' (' . $valuePlaceholder . ') ';
                }

                continue;

            }

            if ($value instanceof Raw) {
                $criteria .= "{$statement['joiner']} {$key} {$statement['operator']} $value ";
                continue;
            }


            // Usual where like criteria
            if ($bindValues === false) {

                // Specially for joins - we are not binding values, lets sanitize then
                $value = $this->wrapSanitizer($value);
                $criteria .= $statement['joiner'] . ' ' . $key . ' ' . $statement['operator'] . ' ' . $value . ' ';

                continue;
            }

            if ($statement['key'] instanceof Raw) {

                if ($statement['operator'] !== null) {
                    $criteria .= "{$statement['joiner']} {$key} {$statement['operator']} ? ";
                    $bindings[] = $statement['key']->getBindings();
                    $bindings[] = [$value];
                } else {
                    $criteria .= $statement['joiner'] . ' ' . $key . ' ';
                    $bindings[] = $statement['key']->getBindings();
                }

                continue;

            }

            // WHERE
            $valuePlaceholder = '?';
            $bindings[] = [$value];
            $criteria .= $statement['joiner'] . ' ' . $key . ' ' . $statement['operator'] . ' ' . $valuePlaceholder . ' ';
        }

        // Clear all white spaces, and, or from beginning and white spaces from ending
        $criteria = \preg_replace('/^(\s?AND ?|\s?OR ?)|\s$/i', '', $criteria);

        return [$criteria, array_merge(...$bindings)];
    }

    /**
     * Build criteria string and binding with various types added, like WHERE and Having
     *
     * @param array $statements
     * @param string $key
     * @param string $type
     * @param bool $bindValues
     *
     * @return array
     * @throws Exception
     */
    protected function buildCriteriaWithType(array $statements, $key, $type, $bindValues = true): array
    {
        $criteria = '';
        $bindings = [];

        if (isset($statements[$key]) === true) {
            // Get the generic/adapter agnostic criteria string from parent
            list($criteria, $bindings) = $this->buildCriteria($statements[$key], $bindValues);

            if ($criteria !== null) {
                $criteria = $type . ' ' . $criteria;
            }
        }

        return [$criteria, $bindings];
    }

    /**
     * Build join string
     *
     * @param array $statements
     *
     * @return string
     * @throws Exception
     */
    protected function buildJoin(array $statements): string
    {
        $sql = '';

        if (\array_key_exists('joins', $statements) === false || \count($statements['joins']) === 0) {
            return $sql;
        }

        foreach ((array)$statements['joins'] as $joinArr) {
            if (\is_array($joinArr['table']) === true) {
                list($mainTable, $aliasTable) = $joinArr['table'];
                $table = $this->wrapSanitizer($mainTable) . ' AS ' . $this->wrapSanitizer($aliasTable);
            } else {
                $table = $joinArr['table'] instanceof Raw ? (string)$joinArr['table'] : $this->wrapSanitizer($joinArr['table']);
            }

            /* @var $joinBuilder \Pecee\Pixie\QueryBuilder\QueryBuilderHandler */
            $joinBuilder = $joinArr['joinBuilder'];

            $sqlArr = [
                $sql,
                strtoupper($joinArr['type']),
                'JOIN',
                $table,
                'ON',
                $joinBuilder->getQuery('criteriaOnly', false)->getSql(),
            ];

            $sql = $this->concatenateQuery($sqlArr);
        }

        return $sql;
    }

    /**
     * Join different part of queries with a space.
     *
     * @param array $pieces
     *
     * @return string
     */
    protected function concatenateQuery(array $pieces): string
    {
        $str = '';
        foreach ($pieces as $piece) {
            $str = trim($str) . ' ' . trim($piece);
        }

        return trim($str);
    }

    /**
     * Build just criteria part of the query
     *
     * @param array $statements
     * @param bool $bindValues
     *
     * @return array
     * @throws Exception
     */
    public function criteriaOnly(array $statements, $bindValues = true): array
    {
        $sql = $bindings = [];
        if (isset($statements['criteria']) === false) {
            return compact('sql', 'bindings');
        }

        list($sql, $bindings) = $this->buildCriteria($statements['criteria'], $bindValues);

        return compact('sql', 'bindings');
    }

    /**
     * Build delete query
     *
     * @param array $statements
     *
     * @return array
     * @throws Exception
     */
    public function delete(array $statements): array
    {
        $table = end($statements['tables']);

        // WHERE
        list($whereCriteria, $whereBindings) = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        $sqlArray = ['DELETE FROM', $this->wrapSanitizer($table), $whereCriteria];
        $sql = $this->concatenateQuery($sqlArray);
        $bindings = $whereBindings;

        return compact('sql', 'bindings');
    }

    /**
     * Build a generic insert/ignore/replace query
     *
     * @param array $statements
     * @param array $data
     * @param string $type
     *
     * @return array
     * @throws Exception
     */
    private function doInsert(array $statements, array $data, $type): array
    {
        $table = end($statements['tables']);

        $bindings = $keys = $values = [];

        foreach ($data as $key => $value) {
            $keys[] = $key;
            if ($value instanceof Raw) {
                $values[] = (string)$value;
            } else {
                $values[] = '?';
                $bindings[] = $value;
            }
        }

        $sqlArray = [
            $type . ' INTO',
            $this->wrapSanitizer($table),
            '(' . $this->arrayStr($keys) . ')',
            'VALUES',
            '(' . $this->arrayStr($values, ',', false) . ')',
        ];

        if (isset($statements['onduplicate']) === true) {

            if (\count($statements['onduplicate']) < 1) {
                throw new Exception('No data given.', 4);
            }

            list($updateStatement, $updateBindings) = $this->getUpdateStatement($statements['onduplicate']);
            $sqlArray[] = 'ON DUPLICATE KEY UPDATE ' . $updateStatement;
            $bindings = array_merge($bindings, $updateBindings);

        }

        $sql = $this->concatenateQuery($sqlArray);

        return compact('sql', 'bindings');
    }

    /**
     * Build fields assignment part of SET ... or ON DUBLICATE KEY UPDATE ... statements
     *
     * @param array $data
     *
     * @return array
     */
    private function getUpdateStatement(array $data): array
    {
        $bindings = [];
        $statement = '';

        foreach ($data as $key => $value) {

            $statement .= $this->wrapSanitizer($key) . '=';

            if ($value instanceof Raw) {
                $statement .= $value . ',';
            } else {
                $statement .= '?,';
                $bindings[] = $value;
            }
        }

        $statement = trim($statement, ',');

        return [$statement, $bindings];
    }

    /**
     * Build insert query
     *
     * @param array $statements
     * @param array $data
     *
     * @return array
     * @throws Exception
     */
    public function insert(array $statements, array $data): array
    {
        return $this->doInsert($statements, $data, 'INSERT');
    }

    /**
     * Build insert and ignore query
     *
     * @param array $statements
     * @param array $data
     *
     * @return array
     * @throws Exception
     */
    public function insertIgnore(array $statements, array $data): array
    {
        return $this->doInsert($statements, $data, 'INSERT IGNORE');
    }

    /**
     * Build replace query
     *
     * @param array $statements
     * @param array $data
     *
     * @return array
     * @throws Exception
     */
    public function replace(array $statements, array $data): array
    {
        return $this->doInsert($statements, $data, 'REPLACE');
    }

    /**
     * Build select query string and bindings
     *
     * @param array $statements
     *
     * @throws Exception
     * @return array
     */
    public function select(array $statements): array
    {
        if (array_key_exists('selects', $statements) === false) {
            $statements['selects'] = ['*'];
        }

        // From
        $fromEnabled = false;
        $tables = '';

        if (isset($statements['tables']) === true) {
            $tables = [];

            foreach ((array)$statements['tables'] as $table) {
                if ($table instanceof Raw) {
                    $t = $table;
                } else {
                    $prefix = $statements['aliases'][$table] ?? null;

                    if ($prefix !== null) {
                        $t = sprintf('`%s` AS `%s`', $table, strtolower($prefix));
                    } else {
                        $t = sprintf('`%s`', $table);
                    }
                }

                $tables[] = $t;
            }

            $tables = implode(',', $tables);
            $fromEnabled = true;
        }

        // SELECT
        $selects = $this->arrayStr($statements['selects'], ', ');

        // WHERE
        list($whereCriteria, $whereBindings) = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        // GROUP BY
        $groupBys = $this->arrayStr($statements['groupBys'], ', ');
        if ($groupBys !== '' && isset($statements['groupBys']) === true) {
            $groupBys = 'GROUP BY ' . $groupBys;
        }

        // ORDER BY
        $orderBys = '';
        if (isset($statements['orderBys']) && \is_array($statements['orderBys'])) {
            foreach ($statements['orderBys'] as $orderBy) {
                $orderBys .= $this->wrapSanitizer($orderBy['field']) . ' ' . $orderBy['type'] . ', ';
            }

            if ($orderBys = trim($orderBys, ', ')) {
                $orderBys = 'ORDER BY ' . $orderBys;
            }
        }

        // LIMIT AND OFFSET
        $limit = isset($statements['limit']) ? 'LIMIT ' . $statements['limit'] : '';
        $offset = isset($statements['offset']) ? 'OFFSET ' . $statements['offset'] : '';

        // HAVING
        list($havingCriteria, $havingBindings) = $this->buildCriteriaWithType($statements, 'havings', 'HAVING');

        // JOINS
        $joinString = $this->buildJoin($statements);

        $sqlArray = [
            'SELECT' . (isset($statements['distinct']) ? ' DISTINCT' : ''),
            $selects,
            $fromEnabled ? 'FROM' : '',
            $tables,
            $joinString,
            $whereCriteria,
            $groupBys,
            $havingCriteria,
            $orderBys,
            $limit,
            $offset,
        ];

        $sql = $this->concatenateQuery($sqlArray);

        $bindings = array_merge(
            $whereBindings,
            $havingBindings
        );

        return compact('sql', 'bindings');
    }

    /**
     * Build update query
     *
     * @param array $statements
     * @param array $data
     *
     * @return array
     * @throws Exception
     */
    public function update(array $statements, array $data): array
    {
        if (\count($data) < 1) {
            throw new Exception('No data given.', 4);
        }

        $table = end($statements['tables']);

        // UPDATE
        list($updateStatement, $bindings) = $this->getUpdateStatement($data);

        // WHERE
        list($whereCriteria, $whereBindings) = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        // LIMIT
        $limit = isset($statements['limit']) ? 'LIMIT ' . $statements['limit'] : '';

        $sqlArray = [
            'UPDATE',
            $this->wrapSanitizer($table),
            'SET ' . $updateStatement,
            $whereCriteria,
            $limit,
        ];

        $sql = $this->concatenateQuery($sqlArray);

        $bindings = array_merge($bindings, $whereBindings);

        return compact('sql', 'bindings');
    }

    /**
     * Wrap values with adapter's sanitizer like, '`'
     *
     * @param string|Raw|\Closure $value
     *
     * @return string|\Closure
     */
    public function wrapSanitizer($value)
    {
        // Its a raw query, just cast as string, object has __toString()
        if ($value instanceof Raw) {
            return (string)$value;
        }

        if ($value instanceof \Closure) {
            return $value;
        }

        // Separate our table and fields which are joined with a ".", like my_table.id
        $valueArr = explode('.', $value, 2);

        foreach ($valueArr as $key => $subValue) {
            // Don't wrap if we have *, which is not a usual field
            $valueArr[$key] = trim($subValue) === '*' ? $subValue : static::SANITIZER . $subValue . static::SANITIZER;
        }

        // Join these back with "." and return
        return implode('.', $valueArr);
    }
}
