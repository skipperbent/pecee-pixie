<?php
namespace Pecee\Pixie\QueryBuilder\Adapters;

use Pecee\Pixie\Exception;
use Pecee\Pixie\QueryBuilder\Raw;
use Pecee\Pixie\QueryBuilder\Adapters\BaseAdapter;

/**
 * Class Sqlserver
 *
 * @package Pecee\Pixie\QueryBuilder\Adapters
 */
class Sqlserver extends BaseAdapter
{
    /**
     * @var string
     */
    public const SANITIZER = '';

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
        $hasDistincts = false;

        if (isset($statements['distincts']) === true && \count($statements['distincts']) > 0) {
            $hasDistincts = true;

            if (isset($statements['selects']) === true && \count($statements['selects']) > 0) {
                $statements['selects'] = array_merge($statements['distincts'], $statements['selects']);
            } else {
                $statements['selects'] = $statements['distincts'];
            }

        } else if (isset($statements['selects']) === false) {
            $statements['selects'] = ['*'];
        }

        // From
        $fromEnabled = false;
        $tables = '';

        if (isset($statements['tables']) === true) {
            $tablesFound = [];

            foreach ((array)$statements['tables'] as $table) {
                if ($table instanceof Raw) {
                    $t = $table;
                } else {
                    $prefix = $statements['aliases'][$table] ?? null;

                    if ($prefix !== null) {
                        $t = sprintf('%s AS %s', $table, strtolower($prefix));
                    } else {
                        $t = sprintf('%s', $table);
                    }
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
            $this->buildQueryPart(static::QUERY_PART_TOP, $statements),
            $this->arrayStr($statements['selects'], ', '),
            $fromEnabled ? 'FROM' : '',
            $tables,
            $this->buildQueryPart(static::QUERY_PART_JOIN, $statements),
            $whereCriteria,
            $this->buildQueryPart(static::QUERY_PART_GROUPBY, $statements),
            $havingCriteria,
            $this->buildQueryPart(static::QUERY_PART_ORDERBY, $statements),
            $this->buildQueryPart(static::QUERY_PART_OFFSET, $statements),
        ]);

        $sql = $this->buildUnion($statements, $sql);

        $bindings = array_merge(
            $whereBindings,
            $havingBindings
        );

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

        if($columns !== null) {
            foreach($columns as $key => $column) {
                $columns[$key] = $this->wrapSanitizer($column);
            }

            $columnsQuery = implode(', ', $columns);
        }

        // WHERE
        [$whereCriteria, $whereBindings] = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        $sql = $this->concatenateQuery([
            'DELETE ',
            $columnsQuery,
            ' FROM',
            $this->wrapSanitizer($table),
            $this->buildQueryPart(static::QUERY_PART_JOIN, $statements),
            $whereCriteria,
            $this->buildQueryPart(static::QUERY_PART_GROUPBY, $statements),
            $this->buildQueryPart(static::QUERY_PART_ORDERBY, $statements),
            $this->buildQueryPart(static::QUERY_PART_OFFSET, $statements),
        ]);
        $bindings = $whereBindings;

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
            $this->wrapSanitizer($table),
            $this->buildQueryPart(static::QUERY_PART_JOIN, $statements),
            'SET ' . $updateStatement,
            $whereCriteria,
            $this->buildQueryPart(static::QUERY_PART_GROUPBY, $statements),
            $this->buildQueryPart(static::QUERY_PART_ORDERBY, $statements),
            $this->buildQueryPart(static::QUERY_PART_OFFSET, $statements),
        ];

        $sql = $this->concatenateQuery($sqlArray);

        $bindings = array_merge($bindings, $whereBindings);

        return compact('sql', 'bindings');
    }

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
            $valueArr[$key] = trim($subValue) === '*' ? $subValue : '[' . $subValue . ']';
        }

        // Join these back with "." and return
        return implode('.', $valueArr);
    }
}
