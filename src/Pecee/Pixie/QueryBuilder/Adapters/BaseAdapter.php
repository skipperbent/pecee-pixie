<?php

namespace Pecee\Pixie\QueryBuilder\Adapters;

use Pecee\Pixie\Connection;
use Pecee\Pixie\Exception;
use Pecee\Pixie\QueryBuilder\NestedCriteria;
use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;
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
    public const SANITIZER = '`';

    /**
     * @var string
     */
    protected const QUERY_PART_JOIN = 'JOIN';

    /**
     * @var string
     */
    protected const QUERY_PART_ORDERBY = 'ORDERBY';

    /**
     * @var string
     */
    protected const QUERY_PART_LIMIT = 'LIMIT';

    /**
     * @var string
     */
    protected const QUERY_PART_OFFSET = 'OFFSET';

    /**
     * @var string
     */
    protected const QUERY_PART_FOR = 'FOR';

    /**
     * @var string
     */
    protected const QUERY_PART_GROUPBY = 'GROUPBY';

    /**
     * @var string
     */
    protected const QUERY_PART_TOP = 'TOP';

    /**
     * @var Connection
     */
    protected $connection;

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
     * Alias prefix
     * @var string|null
     */
    protected $aliasPrefix;

    /**
     * BaseAdapter constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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
    protected function arrayStr(array $pieces, string $glue = ', ', bool $wrapSanitizer = true): string
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
     * @return array
     * @throws Exception
     */
    protected function buildCriteria(array $statements, bool $bindValues = true): array
    {
        $criteria = [];
        $bindings = [[]];

        foreach (array_values($statements) as $i => $statement) {

            if ($i === 0 && isset($statement['condition'])) {
                $criteria[] = $statement['condition'];
            }

            $joiner = ($i === 0) ? trim(str_ireplace(['and', 'or'], '', $statement['joiner'])) : $statement['joiner'];

            if ($joiner !== '') {
                $criteria[] = $joiner;
            }

            if (isset($statement['columns']) === true) {
                $criteria[] = sprintf('(%s)', $this->arrayStr((array)$statement['columns']));
                continue;
            }

            $key = $statement['key'];

            if ($key instanceof Raw === false) {
                $key = $this->wrapSanitizer($key);

                // Add alias non-existing
                if (is_string($key) && $this->aliasPrefix !== null && strpos($key, '.') === false) {
                    $key = $this->aliasPrefix . '.' . $key;
                }
            } else {
                $bindings[] = $key->getBindings();
            }

            $value = $statement['value'];

            if ($value instanceof Raw) {
                $bindings[] = $value->getBindings();
            }

            if ($value === null && $key instanceof \Closure) {

                /**
                 * We have a closure, a nested criteria
                 * Build a new NestedCriteria class, keep it by reference so any changes made in the closure should reflect here
                 */

                $nestedCriteria = new NestedCriteria($this->connection);

                // Call the closure with our new nestedCriteria object
                $key($nestedCriteria);

                // Get the criteria only query from the nestedCriteria object
                $queryObject = $nestedCriteria->getQuery('criteriaOnly', true);

                // Merge the bindings we get from nestedCriteria object
                $bindings[] = $queryObject->getBindings();

                // Append the sql we get from the nestedCriteria object
                $criteria[] = "({$queryObject->getSql()})";

                continue;
            }

            if (\is_array($value) === true) {

                // Where in or between like query
                $criteria[] = "$key {$statement['operator']}";

                if ($statement['operator'] === 'BETWEEN') {
                    $bindings[] = $statement['value'];
                    $criteria[] = '? AND ?';
                    continue;
                }

                $valuePlaceholder = '';

                foreach ((array)$statement['value'] as $subValue) {
                    $valuePlaceholder .= '?, ';
                    $bindings[] = [$subValue];
                }

                $valuePlaceholder = trim($valuePlaceholder, ', ');
                $criteria[] = "($valuePlaceholder)";

                continue;
            }

            if ($bindValues === false || $value instanceof Raw) {

                // Usual where like criteria specially for joins - we are not binding values, lets sanitize then
                $value = ($bindValues === false) ? $this->wrapSanitizer($value) : $value;
                $criteria[] = "{$key} {$statement['operator']} $value";

                if ($value instanceof Raw) {
                    $bindings[] = $value->getBindings();
                }

                continue;
            }

            if ($key instanceof Raw) {

                if ($statement['operator'] !== null) {
                    $criteria[] = "{$key} {$statement['operator']} ?";
                    $bindings[] = [$value];
                    continue;
                }

                $criteria[] = $key;
                continue;

            }

            // Check for objects that implement the __toString() magic method
            if (\is_object($value) === true && \method_exists($value, '__toString') === true) {
                $value = $value->__toString();
            }

            // WHERE
            $bindings[] = [$value];
            $criteria[] = "$key {$statement['operator']} ?";
        }

        // Clear all white spaces, and, or from beginning and white spaces from ending

        return [
            implode(' ', $criteria),
            array_merge(...$bindings),
        ];
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
            [$criteria, $bindings] = $this->buildCriteria($statements[$key], $bindValues);

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
     * @param array $bindings
     *
     * @return string
     * @throws Exception
     */
    protected function buildJoin(array $statements, array &$bindings): string
    {
        $sql = '';

        $newBindings = [];

        if (isset($statements['joins']) === false) {
            return $sql;
        }

        foreach ((array)$statements['joins'] as $joinArr) {
            if (\is_array($joinArr['table']) === true) {
                [$mainTable, $aliasTable] = $joinArr['table'];
                $table = $this->wrapSanitizer($mainTable) . ' AS ' . $this->wrapSanitizer($aliasTable);
            } else {
                $table = $joinArr['table'] instanceof Raw ? (string)$joinArr['table'] : $this->wrapSanitizer($joinArr['table']);
            }

            /* @var $joinBuilder QueryBuilderHandler */
            $joinBuilder = $joinArr['joinBuilder'];

            $valueSql = '';

            if ($joinBuilder instanceof QueryBuilderHandler) {
                $valueQuery = $joinBuilder->getQuery('criteriaOnly', false);
                $valueSql = $valueQuery->getSql();
                $newBindings[] = $valueQuery->getBindings();
            }

            $sqlArr = [
                $sql,
                strtoupper($joinArr['type']),
                'JOIN',
                $table,
                $valueSql,
            ];

            $sql = $this->concatenateQuery($sqlArr);
        }

        $bindings = array_merge($bindings, ...$newBindings);

        return $sql;
    }

    /**
     * Return table name with alias
     * eg. foo as f
     *
     * @param string $table
     * @param array $statements
     *
     * @return string
     */
    protected function buildAliasedTableName(string $table, array $statements): string
    {
        $this->aliasPrefix = $statements['aliases'][$table] ?? null;
        if ($this->aliasPrefix !== null) {
            return sprintf('%s AS %s', $this->wrapSanitizer($table), $this->wrapSanitizer(strtolower($this->aliasPrefix)));
        }

        return sprintf('%s', $this->wrapSanitizer($table));
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

        [$sql, $bindings] = $this->buildCriteria($statements['criteria'], $bindValues);

        return compact('sql', 'bindings');
    }

    /**
     * Build delete query
     *
     * @param array $statements
     * @param array|null $columns
     *
     * @return array
     * @throws Exception
     */
    public function delete(array $statements, array $columns = null): array
    {
        $table = end($statements['tables']);

        $columnsQuery = '';

        if ($columns !== null) {
            $columnsQuery = $this->arrayStr($columns);
        }

        // WHERE
        [$whereCriteria, $bindings] = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        $sql = $this->concatenateQuery([
            'DELETE ',
            $columnsQuery,
            ' FROM',
            $this->wrapSanitizer($table),
            $this->buildQueryPart(static::QUERY_PART_JOIN, $statements, $bindings),
            $whereCriteria,
            $this->buildQueryPart(static::QUERY_PART_GROUPBY, $statements, $bindings),
            $this->buildQueryPart(static::QUERY_PART_ORDERBY, $statements, $bindings),
            $this->buildQueryPart(static::QUERY_PART_LIMIT, $statements, $bindings),
            $this->buildQueryPart(static::QUERY_PART_OFFSET, $statements, $bindings),
        ]);

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
                $bindings += $value->getBindings();
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
            '(' . $this->arrayStr($values, ', ', false) . ')',
        ];

        if (isset($statements['onduplicate']) === true) {

            if (\count($statements['onduplicate']) < 1) {
                throw new Exception('No data given.', 4);
            }

            [$updateStatement, $updateBindings] = $this->getUpdateStatement($statements['onduplicate']);
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
        $statements = [];

        foreach ($data as $key => $value) {

            $statement = $this->wrapSanitizer($key) . ' = ';

            if ($value instanceof Raw) {
                $statements[] = $statement . $value;
                $bindings += $value->getBindings();
            } else {
                $statements[] = $statement . '?';
                $bindings[] = $value;
            }
        }

        $statement = trim($this->arrayStr($statements, ', ', false));

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
     * Sets select statements and returns status of distinct tables.
     *
     * @param array $statements
     * @param array $bindings
     * @return bool Returns true if distinct tables are found.
     */
    protected function setSelectStatement(array &$statements, array &$bindings): bool
    {
        $hasDistincts = false;

        if (isset($statements['distincts']) === true && \count($statements['distincts']) > 0) {
            $hasDistincts = true;

            if (isset($statements['selects']) === true && \count($statements['selects']) > 0) {
                $statements['selects'] = array_merge($statements['distincts'], $statements['selects']);
            } else {
                $statements['selects'] = $statements['distincts'];
            }

        } else {
            if (isset($statements['selects']) === false) {
                $statements['selects'] = ['*'];
            }
        }

        foreach ((array)$statements['selects'] as $select) {
            if ($select instanceof Raw) {
                $bindings += $select->getBindings();
            }
        }

        return $hasDistincts;
    }

    /**
     * Build select query string and bindings
     *
     * @param array $statements
     *
     * @return array
     * @throws Exception
     */
    public function select(array $statements): array
    {
        $bindings = [];

        $hasDistincts = $this->setSelectStatement($statements, $bindings);

        // From
        $fromEnabled = false;
        $tables = '';

        if (isset($statements['tables']) === true) {
            $tablesFound = [];
            foreach ((array)$statements['tables'] as $table) {
                if ($table instanceof Raw) {
                    $t = $table;
                } else {
                    $t = $this->buildAliasedTableName($table, $statements);
                }

                $tablesFound[] = $t;
            }

            $tables = implode(',', $tablesFound);
            $fromEnabled = true;
        }

        // WHERE
        [$whereCriteria, $whereBindings] = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        // HAVING
        [$havingCriteria, $havingBindings] = $this->buildCriteriaWithType($statements, 'havings', 'HAVING');

        $sql = $this->concatenateQuery([
            'SELECT' . ($hasDistincts === true ? ' DISTINCT' : ''),
            $this->arrayStr($statements['selects'], ', '),
            $fromEnabled ? 'FROM' : '',
            $tables,
            $this->buildQueryPart(static::QUERY_PART_JOIN, $statements, $bindings),
            $whereCriteria,
            $this->buildQueryPart(static::QUERY_PART_GROUPBY, $statements, $bindings),
            $havingCriteria,
            $this->buildQueryPart(static::QUERY_PART_ORDERBY, $statements, $bindings),
            $this->buildQueryPart(static::QUERY_PART_LIMIT, $statements, $bindings),
            $this->buildQueryPart(static::QUERY_PART_OFFSET, $statements, $bindings),
            $this->buildQueryPart(static::QUERY_PART_FOR, $statements, $bindings),
        ]);

        $sql = $this->buildUnion($statements, $sql);

        $bindings = array_merge(
            $bindings,
            $whereBindings,
            $havingBindings
        );

        return compact('sql', 'bindings');
    }

    /**
     * Returns specific part of a query like JOIN, LIMIT, OFFSET etc.
     *
     * @param string $section
     * @param array $statements
     * @param array $bindings
     * @return string
     * @throws Exception
     */
    protected function buildQueryPart(string $section, array $statements, array &$bindings): string
    {
        switch ($section) {
            case static::QUERY_PART_JOIN:
                return $this->buildJoin($statements, $bindings);
            case static::QUERY_PART_TOP:
                return isset($statements['limit']) ? 'TOP ' . $statements['limit'] : '';
            case static::QUERY_PART_LIMIT:
                return isset($statements['limit']) ? 'LIMIT ' . $statements['limit'] : '';
            case static::QUERY_PART_OFFSET:
                return isset($statements['offset']) ? 'OFFSET ' . $statements['offset'] : '';
            case static::QUERY_PART_ORDERBY:
                $orderBys = '';
                if (isset($statements['orderBys']) === true && \is_array($statements['orderBys']) === true) {
                    foreach ($statements['orderBys'] as $orderBy) {
                        $orderBys .= $this->wrapSanitizer($orderBy['field']) . ' ' . $orderBy['type'] . ', ';
                    }

                    $orderBys = trim($orderBys, ', ');
                    if ($orderBys !== '') {
                        $orderBys = 'ORDER BY ' . $orderBys;
                    }
                }

                return $orderBys;
            case static::QUERY_PART_GROUPBY:
                $groupBys = $this->arrayStr($statements['groupBys'], ', ');
                if ($groupBys !== '' && isset($statements['groupBys']) === true) {
                    $groupBys = 'GROUP BY ' . $groupBys;
                }

                return $groupBys;
            case static::QUERY_PART_FOR:
                return isset($statements['for']) ? ' FOR ' . $statements['for'][0] : '';
        }

        return '';
    }

    /**
     * Adds union query to sql statement
     *
     * @param array $statements
     * @param string $sql
     * @return string
     * @throws Exception
     */
    protected function buildUnion(array $statements, string $sql): string
    {
        if (isset($statements['unions']) === false || \count($statements['unions']) === 0) {
            return $sql;
        }

        foreach ((array)$statements['unions'] as $i => $union) {
            /* @var $queryBuilder QueryBuilderHandler */
            $queryBuilder = $union['query'];

            if ($i === 0) {
                $sql .= ')';
            }

            $type = ($union['type'] !== QueryBuilderHandler::UNION_TYPE_NONE) ? $union['type'] . ' ' : '';
            $sql .= sprintf(' UNION %s(%s)', $type, $queryBuilder->getQuery('select')->getRawSql());
        }

        return sprintf('(%s', $sql);
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
        if (\count($data) === 0) {
            throw new Exception('No data given.', 4);
        }

        $table = end($statements['tables']);

        // UPDATE
        [$updateStatement, $bindings] = $this->getUpdateStatement($data);

        // WHERE
        [$whereCriteria, $whereBindings] = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        $sqlArray = [
            'UPDATE',
            $this->buildAliasedTableName($table, $statements),
            $this->buildQueryPart(static::QUERY_PART_JOIN, $statements, $bindings),
            'SET ' . $updateStatement,
            $whereCriteria,
            $this->buildQueryPart(static::QUERY_PART_GROUPBY, $statements, $bindings),
            $this->buildQueryPart(static::QUERY_PART_ORDERBY, $statements, $bindings),
            $this->buildQueryPart(static::QUERY_PART_LIMIT, $statements, $bindings),
            $this->buildQueryPart(static::QUERY_PART_OFFSET, $statements, $bindings),
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