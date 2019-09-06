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
     * @var string
     */
    protected const QUERY_PART_FETCH_NEXT = 'FETCH NEXT';

    /**
     * Overridden method for SQL SERVER correct usage of: OFFSET x ROWS FETCH NEXT y ROWS ONLY
     * @param string $section
     * @param array $statements
     * @return string
     * @throws Exception
     */
    protected function buildQueryPart(string $section, array $statements): string
    {
        switch ($section) {
            case static::QUERY_PART_OFFSET:
                return isset($statements['offset']) ? 'OFFSET '.$statements['offset'].' ROWS' : '';
            case static::QUERY_PART_FETCH_NEXT:
                return isset($statements['offset']) ? 'FETCH NEXT '.$statements['limit'].' ROWS ONLY' : '';
            default:
                return parent::buildQueryPart($section, $statements);
        }
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
            $this->buildQueryPart(static::QUERY_PART_FETCH_NEXT, $statements),
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
