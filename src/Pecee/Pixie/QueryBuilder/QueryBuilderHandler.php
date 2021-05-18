<?php

namespace Pecee\Pixie\QueryBuilder;

use PDO;
use Pecee\Pixie\Connection;
use Pecee\Pixie\Event\EventHandler;
use Pecee\Pixie\Exception;
use Pecee\Pixie\Exceptions\ColumnNotFoundException;
use Pecee\Pixie\Exceptions\ConnectionException;
use Pecee\Pixie\Exceptions\TransactionHaltException;

/**
 * Class QueryBuilderHandler
 *
 * @package Pecee\Pixie\QueryBuilder
 */
class QueryBuilderHandler implements IQueryBuilderHandler
{
    /**
     * Default union type
     *
     * @var string
     */
    public const UNION_TYPE_NONE = '';

    /**
     * Union type distinct
     *
     * @var string
     */
    public const UNION_TYPE_DISTINCT = 'DISTINCT';

    /**
     * Union type all
     *
     * @var string
     */
    public const UNION_TYPE_ALL = 'ALL';
    /**
     * @var Connection|null
     */
    protected $connection;

    /**
     * @var array
     */
    protected $statements = [
        'groupBys' => [],
        'unions'   => [],
    ];

    /**
     * @var null|\PDOStatement
     */
    protected $pdoStatement;

    /**
     * @var null|string
     */
    protected $tablePrefix;

    /**
     * @var \Pecee\Pixie\QueryBuilder\Adapters\BaseAdapter
     */
    protected $adapterInstance;

    /**
     * The PDO fetch parameters to use
     *
     * @var array
     */
    protected $fetchParameters = [PDO::FETCH_OBJ];

    /**
     * If true calling from, select etc. will overwrite any existing values from previous calls in query.
     *
     * @var bool
     */
    protected $overwriteEnabled = false;

    /**
     * @param Connection|null $connection
     *
     * @throws Exception
     */
    final public function __construct(Connection $connection = null)
    {
        $this->connection = $connection ?? Connection::getStoredConnection();

        if ($this->connection === null) {
            throw new ConnectionException('No database connection found.', 404);
        }

        // Connect to database
        $this->connection->connect();

        $adapterConfig = $this->connection->getAdapterConfig();

        if (isset($adapterConfig['prefix']) === true) {
            $this->tablePrefix = $adapterConfig['prefix'];
        }

        if (isset($adapterConfig['query_overwriting']) === true) {
            $this->overwriteEnabled = (bool)$adapterConfig['query_overwriting'];
        }

        // Query builder adapter instance
        $adapterClass = $this->connection->getAdapter()->getQueryAdapterClass();
        $this->adapterInstance = new $adapterClass($this->connection);

        $this->connection->getPdoInstance()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Fetch query results as object of specified type
     *
     * @param string $className
     * @param array $constructorArgs
     *
     * @return static
     */
    public function asObject(string $className, array $constructorArgs = []): QueryBuilderHandler
    {
        return $this->setFetchMode(PDO::FETCH_CLASS, $className, $constructorArgs);
    }

    /**
     * Add fetch parameters to the PDO-query.
     *
     * @param mixed $parameters ...
     *
     * @return static
     */
    public function setFetchMode($parameters = null): self
    {
        $this->fetchParameters = \func_get_args();

        return $this;
    }

    /**
     * Removes existing statement if overwrite is set to enabled.
     *
     * @param string $type Statement type
     * @param string $key Key to search for
     * @param mixed $value Value to match
     */
    protected function removeExistingStatement(string $type, string $key, $value): void
    {
        if ($this->overwriteEnabled === false || isset($this->statements[$type]) === false) {
            return;
        }

        foreach ($this->statements[$type] as $index => $statement) {
            if ($statement[$key] instanceof \Closure) {
                $nestedCriteria = new QueryBuilderHandler($this->connection);

                $statement[$key]($nestedCriteria);

                if (isset($nestedCriteria->getStatements()[$type])) {
                    foreach ($nestedCriteria->getStatements()[$type] as $subStatement) {
                        if ($subStatement[$key] === $value) {
                            unset($this->statements[$type][$index]);

                            return;
                        }
                    }
                }

            }
            if ($statement[$key] === $value) {
                unset($this->statements[$type][$index]);
                return;
            }
        }
    }

    /**
     * Get count of all the rows for the current query
     *
     * @param string $field
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return integer
     * @throws Exception
     */
    public function count(string $field = '*'): int
    {
        return (int)$this->aggregate('count', $field);
    }

    /**
     * Performs special queries like COUNT, SUM etc based on the current query.
     *
     * @param string $type
     * @param string $field
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return float
     * @throws Exception
     */
    protected function aggregate(string $type, string $field = '*'): float
    {
        // Verify that field exists
        if ($field !== '*' && isset($this->statements['selects']) === true && \in_array($field, $this->statements['selects'], true) === false) {
            throw new ColumnNotFoundException(sprintf('Failed to count query - the column %s hasn\'t been selected in the query.', $field));
        }

        if (isset($this->statements['tables']) === false) {
            throw new Exception('No table selected');
        }

        $count = $this
            ->table($this->subQuery($this, 'count'))
            ->select([$this->raw(sprintf('%s(%s) AS ' . $this->adapterInstance->wrapSanitizer('field'), strtoupper($type), $field))])
            ->first();

        return isset($count->field) === true ? (float)$count->field : 0;
    }

    /**
     * Get the alias for the current query
     *
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return isset($this->statements['aliases']) === true ? array_values($this->statements['aliases'])[0] : null;
    }

    /**
     * Get the table-name for the current query
     *
     * @return string|null
     */
    public function getTable(): ?string
    {
        if (isset($this->statements['tables']) === true) {
            $table = array_values($this->statements['tables'])[0];
            if ($table instanceof Raw === false) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Returns the first row
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return \stdClass|string|null
     * @throws Exception
     */
    public function first()
    {
        $result = $this->limit(1)->get();

        return (\count($result) !== 0) ? $result[0] : null;
    }

    /**
     * Get all rows
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return array
     * @throws Exception
     */
    public function get(): array
    {
        $queryObject = $this->getQuery();
        $this->connection->setLastQuery($queryObject);

        $this->fireEvents(EventHandler::EVENT_BEFORE_SELECT, $queryObject);

        $executionTime = 0.0;
        $startTime = microtime(true);

        if ($this->pdoStatement === null) {

            [$this->pdoStatement, $executionTime] = $this->statement(
                $queryObject->getSql(),
                $queryObject->getBindings()
            );
        }

        $result = \call_user_func_array([$this->pdoStatement, 'fetchAll'], $this->fetchParameters);
        $this->pdoStatement = null;

        $executionTime += microtime(true) - $startTime;

        $this->fireEvents(EventHandler::EVENT_AFTER_SELECT, $queryObject, [
            'execution_time' => $executionTime,
        ]);

        return $result;
    }

    /**
     * Returns Query-object.
     *
     * @param string $type
     * @param array|mixed|null $arguments
     *
     * @return QueryObject
     * @throws Exception
     */
    public function getQuery(string $type = 'select', ...$arguments): QueryObject
    {
        $allowedTypes = [
            'select',
            'insert',
            'insertignore',
            'replace',
            'delete',
            'update',
            'criteriaonly',
        ];

        if (\in_array(strtolower($type), $allowedTypes, true) === false) {
            throw new Exception($type . ' is not a known type.', 1);
        }

        $queryArr = $this->adapterInstance->$type($this->statements, ...$arguments);

        return new QueryObject($queryArr['sql'], $queryArr['bindings'], $this->getConnection());
    }

    /**
     * Get connection object
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Set connection object
     *
     * @param Connection $connection
     *
     * @return static
     */
    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Fires event by given event name
     *
     * @param string $name
     * @param QueryObject $queryObject
     * @param array $eventArguments
     *
     * @return void
     */
    public function fireEvents(string $name, QueryObject $queryObject, array $eventArguments = []): void
    {
        $this->connection->getEventHandler()->fireEvents($name, $queryObject, $this, $eventArguments);
    }

    /**
     * Execute statement
     *
     * @param string $sql
     * @param array $bindings
     *
     * @return array PDOStatement and execution time as float
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws Exception
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     */
    public function statement(string $sql, array $bindings = []): array
    {
        try {
            $startTime = microtime(true);
            $pdoStatement = $this->pdo()->prepare($sql);

            /**
             * NOTE:
             * PHP 5.6 & 7 bug: https://bugs.php.net/bug.php?id=38546
             * \PDO::PARAM_BOOL is not supported, use \PDO::PARAM_INT instead
             */
            foreach ($bindings as $key => $value) {
                $pdoStatement->bindValue(
                    \is_int($key) ? $key + 1 : $key,
                    $value,
                    $this->parseParameterType($value)
                );
            }

            $pdoStatement->execute();

            return [
                $pdoStatement,
                microtime(true) - $startTime,
            ];
        } catch (\PDOException $e) {
            throw Exception::create($e, $this->getConnection()->getAdapter()->getQueryAdapterClass(), $this->getLastQuery());
        }
    }

    /**
     * Return PDO instance
     *
     * @return PDO
     */
    public function pdo(): PDO
    {
        return $this->getConnection()->getPdoInstance();
    }

    /**
     * Parse parameter type from value
     *
     * @param mixed $value
     *
     * @return int
     */
    protected function parseParameterType($value): int
    {

        if ($value === null) {
            return PDO::PARAM_NULL;
        }

        if (\is_int($value) === true || \is_bool($value) === true) {
            return PDO::PARAM_INT;
        }

        return PDO::PARAM_STR;
    }

    /**
     * Get query-object from last executed query.
     *
     * @return QueryObject|null
     */
    public function getLastQuery(): ?QueryObject
    {
        return $this->connection->getLastQuery();
    }

    /**
     * Adds LIMIT statement to the current query.
     *
     * @param int $limit
     *
     * @return static
     */
    public function limit(int $limit): self
    {
        $this->statements['limit'] = $limit;

        return $this;
    }

    /**
     * Adds FETCH NEXT statement to the current query.
     *
     * @param int $fetchNext
     *
     * @return static $this
     */
    public function fetchNext(int $fetchNext): self
    {
        $this->statements['fetch_next'] = $fetchNext;

        return $this;
    }

    /**
     * Adds fields to select on the current query (defaults is all).
     * You can use key/value array to create alias.
     * Sub-queries and raw-objects are also supported.
     *
     * Example: ['field' => 'alias'] will become `field` AS `alias`
     *
     * @param string|array|Raw $fields,...
     *
     * @return static
     */
    public function select($fields): self
    {
        if (\is_array($fields) === false) {
            $fields = \func_get_args();
        }

        $fields = $this->addTablePrefix($fields);

        if ($this->overwriteEnabled === true) {
            $this->statements['selects'] = $fields;
        } else {
            $this->addStatement('selects', $fields);
        }

        return $this;
    }

    /**
     * Add table prefix (if given) on given string.
     *
     * @param string|array|Raw|\Closure $values
     * @param bool $tableFieldMix If we have mixes of field and table names with a "."
     *
     * @return array|string
     */
    public function addTablePrefix($values, bool $tableFieldMix = true)
    {
        if ($this->tablePrefix === null) {
            return $values;
        }

        // $value will be an array and we will add prefix to all table names
        // If supplied value is not an array then make it one

        $single = false;
        if (\is_array($values) === false) {
            $values = [$values];

            // We had single value, so should return a single value
            $single = true;
        }

        $return = [];

        foreach ($values as $key => $value) {
            // It's a raw query, just add it to our return array and continue next
            if ($value instanceof Raw || $value instanceof \Closure) {
                $return[$key] = $value;
                continue;
            }

            // If key is not integer, it is likely a alias mapping, so we need to change prefix target
            $target = &$value;

            if (\is_int($key) === false) {
                $target = &$key;
            }

            if (($tableFieldMix === false) || (strpos($target, '.') !== false)) {
                $target = $this->tablePrefix . $target;
            }

            $return[$key] = $value;
        }

        // If we had single value then we should return a single value (end value of the array)
        return $single ? end($return) : $return;
    }

    /**
     * Add new statement to statement-list
     *
     * @param string $key
     * @param string|array $value
     *
     * @return void
     */
    protected function addStatement(string $key, $value): void
    {
        if (array_key_exists($key, $this->statements) === false) {
            $this->statements[$key] = (array)$value;
        } else {
            $this->statements[$key] = array_merge($this->statements[$key], (array)$value);
        }
    }

    /**
     * Sets the table that the query is using
     * Note: to remove a table set the $tables argument to null.
     *
     * @param string|array|null $tables Single table or multiple tables as an array or as multiple parameters
     *
     * @return static
     * @throws Exception
     *
     * ```
     * Examples:
     *  - basic usage
     * ->table('table_one')
     * ->table(['table_one'])
     *
     *  - with aliasing
     * ->table(['table_one' => 'one'])
     * ->table($qb->raw('table_one as one'))
     * ```
     */
    public function table($tables = null): self
    {
        if ($tables === null) {
            return $this->from($tables);
        }

        if (\is_array($tables) === false) {
            // Because a single table is converted to an array anyways, this makes sense.
            $tables = \func_get_args();
        }

        return $this->newQuery()->from($tables);
    }

    /**
     * Adds FROM statement to the current query.
     *
     * @param string|array|null $tables Single table or multiple tables as an array or as multiple parameters
     *
     * @return static
     */
    public function from($tables = null): self
    {
        if ($tables === null) {
            $this->statements['tables'] = null;

            return $this;
        }

        if (\is_array($tables) === false) {
            $tables = \func_get_args();
        }

        $tTables = [];

        foreach ($tables as $key => $value) {
            if (\is_string($key) === true) {
                $this->alias($value, $key);
                $tTables[] = $key;
                continue;
            }

            $tTables[] = $value;
        }

        $tTables = $this->addTablePrefix($tTables, false);
        $this->statements['tables'] = $tTables;

        return $this;
    }

    /**
     * Add or change table alias
     * Example: table AS alias
     *
     * @param string $alias
     * @param string $table
     *
     * @return static
     */
    public function alias(string $alias, ?string $table = null): self
    {
        if ($table === null && isset($this->statements['tables'][0]) === true) {
            $table = $this->statements['tables'][0];
        } else {
            $table = $this->tablePrefix . $table;
        }

        $this->statements['aliases'][$table] = \strtolower($alias);

        return $this;
    }

    /**
     * Creates and returns new query.
     *
     * @return static
     * @throws Exception
     */
    public function newQuery(): self
    {
        return new static($this->connection);
    }

    /**
     * Performs new sub-query.
     * Call this method when you want to add a new sub-query in your where etc.
     *
     * @param QueryBuilderHandler $queryBuilder
     * @param string|null $alias
     *
     * @return Raw
     * @throws Exception
     */
    public function subQuery(QueryBuilderHandler $queryBuilder, $alias = null): Raw
    {
        $sql = '(' . $queryBuilder->getQuery()->getRawSql() . ')';
        if ($alias !== null) {
            $sql .= ' AS ' . $this->adapterInstance->wrapSanitizer($alias);
        }

        return $queryBuilder->raw($sql);
    }

    /**
     * Adds a raw string to the current query.
     * This query will be ignored from any parsing or formatting by the Query builder
     * and should be used in conjunction with other statements in the query.
     *
     * For example: $qb->where('result', '>', $qb->raw('COUNT(`score`)));
     *
     * @param string $value
     * @param array|null|mixed $bindings ...
     *
     * @return Raw
     */
    public function raw(string $value, $bindings = null): Raw
    {
        if (\is_array($bindings) === false) {
            $bindings = \func_get_args();
            array_shift($bindings);
        }

        return new Raw($value, $bindings);
    }

    /**
     * Get the sum for a field in the current query
     *
     * @param string $field
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return float
     * @throws Exception
     */
    public function sum(string $field): float
    {
        return $this->aggregate('sum', $field);
    }

    /**
     * Get the average for a field in the current query
     *
     * @param string $field
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return float
     * @throws Exception
     */
    public function average(string $field): float
    {
        return $this->aggregate('avg', $field);
    }

    /**
     * Get the minimum for a field in the current query
     *
     * @param string $field
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return float
     * @throws Exception
     */
    public function min(string $field): float
    {
        return $this->aggregate('min', $field);
    }

    /**
     * Get the maximum for a field in the current query
     *
     * @param string $field
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return float
     * @throws Exception
     */
    public function max(string $field): float
    {
        return $this->aggregate('max', $field);
    }

    /**
     * Forms delete on the current query.
     *
     * @var array|null $columns
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return \PDOStatement
     */
    public function delete(array $columns = null): \PDOStatement
    {
        /* @var $response \PDOStatement */
        $queryObject = $this->getQuery('delete', $columns);

        $this->connection->setLastQuery($queryObject);

        $this->fireEvents(EventHandler::EVENT_BEFORE_DELETE, $queryObject);

        [$response, $executionTime] = $this->statement($queryObject->getSql(), $queryObject->getBindings());
        $this->fireEvents(EventHandler::EVENT_AFTER_DELETE, $queryObject, [
            'execution_time' => $executionTime,
        ]);

        return $response;
    }

    /**
     * Find by value and field name.
     *
     * @param string|int|float $value
     * @param string           $fieldName
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return \stdClass|string|null
     * @throws Exception
     */
    public function find($value, string $fieldName = 'id')
    {
        return $this->where($fieldName, '=', $value)->first();
    }

    /**
     * Adds WHERE statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param string|null $operator
     * @param mixed|Raw|\Closure|null $value
     *
     * @return static
     */
    public function where($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (\func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if (\is_bool($value) === true) {
            $value = (int)$value;
        }

        return $this->whereHandler($key, $operator, $value);
    }

    /**
     * Handles where statements
     *
     * @param string|Raw|\Closure $key
     * @param string|null $operator
     * @param string|array|Raw|\Closure|null $value
     * @param string $joiner
     *
     * @return static
     */
    protected function whereHandler($key, ?string $operator = null, $value = null, $joiner = 'AND'): self
    {
        $key = $this->addTablePrefix($key);
        $this->removeExistingStatement('wheres', 'key', $key);
        $this->statements['wheres'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * Find all by field name and value
     *
     * @param string           $fieldName
     * @param string|int|float $value
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return array
     * @throws Exception
     */
    public function findAll(string $fieldName, $value): array
    {
        return $this->where($fieldName, '=', $value)->get();
    }

    /**
     * Get event by event name
     *
     * @param string $name
     * @param string|null $table
     *
     * @return callable|null
     */
    public function getEvent(string $name, string $table = null): ?callable
    {
        return $this->connection->getEventHandler()->getEvent($name, $table);
    }

    /**
     * Adds GROUP BY to the current query.
     *
     * @param string|Raw|\Closure|array $field
     *
     * @return static
     */
    public function groupBy($field): self
    {
        if (($field instanceof Raw) === false) {
            $field = $this->addTablePrefix($field);
        }

        if ($this->overwriteEnabled === true) {
            $this->statements['groupBys'] = [];
        }

        if (\is_array($field) === true) {
            $this->statements['groupBys'] = array_merge($this->statements['groupBys'], $field);
        } else {
            $this->statements['groupBys'][] = $field;
        }

        return $this;
    }

    /**
     * Adds new INNER JOIN statement to the current query.
     *
     * @param string|Raw|\Closure $table
     * @param string|JoinBuilder|Raw|\Closure $key
     * @param string|mixed|null $operator
     * @param string|Raw|\Closure|null $value
     *
     * @return static
     * @throws Exception
     */
    public function innerJoin($table, $key, $operator = null, $value = null): self
    {
        return $this->join($table, $key, $operator, $value, 'inner');
    }

    /**
     * Adds new JOIN statement to the current query.
     *
     * @param string|Raw|\Closure|array $table
     * @param string|JoinBuilder|Raw|\Closure|null $key
     * @param string|null $operator
     * @param string|Raw|\Closure $value
     * @param string $type
     *
     * @return static
     * @throws Exception
     *
     * ```
     * Examples:
     * - basic usage
     * ->join('table2', 'table2.person_id', '=', 'table1.id');
     *
     * - as alias 'bar'
     * ->join(['table2','bar'], 'bar.person_id', '=', 'table1.id');
     *
     * - complex usage
     * ->join('another_table', function($table)
     * {
     *  $table->on('another_table.person_id', '=', 'my_table.id');
     *  $table->on('another_table.person_id2', '=', 'my_table.id2');
     *  $table->orOn('another_table.age', '>', $queryBuilder->raw(1));
     * })
     * ```
     */
    public function join($table, $key = null, $operator = null, $value = null, $type = ''): self
    {
        $joinBuilder = null;

        if ($key !== null) {
            $joinBuilder = new JoinBuilder($this->connection);

            /**
             * Build a new JoinBuilder class, keep it by reference so any changes made
             * in the closure should reflect here
             */
            if ($key instanceof \Closure === false) {
                $key = static function (JoinBuilder $joinBuilder) use ($key, $operator, $value): void {
                    $joinBuilder->on($key, $operator, $value);
                };
            }

            // Call the closure with our new joinBuilder object
            $key($joinBuilder);
        }

        $table = $this->addTablePrefix($table, false);

        $this->removeExistingStatement('joins', 'table', $table);

        // Get the criteria only query from the joinBuilder object
        $this->statements['joins'][] = [
            'type'        => $type,
            'table'       => $table,
            'joinBuilder' => $joinBuilder,
        ];

        return $this;
    }

    /**
     * Insert with ignore key/value array
     *
     * @param array $data
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return array|string
     * @throws Exception
     */
    public function insertIgnore(array $data)
    {
        return $this->doInsert($data, 'insertignore');
    }

    /**
     * Performs insert
     *
     * @param array  $data
     * @param string $type
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return array|string|null
     * @throws Exception
     */
    private function doInsert(array $data, string $type)
    {
        // Insert single item

        if (\is_array(current($data)) === false) {
            $queryObject = $this->getQuery($type, $data);

            $this->connection->setLastQuery($queryObject);

            $this->fireEvents(EventHandler::EVENT_BEFORE_INSERT, $queryObject);

            /** @var float $executionTime */
            /** @var \PDOStatement $result */
            [$result, $executionTime] = $this->statement($queryObject->getSql(), $queryObject->getBindings());

            $insertId = $result->rowCount() === 1 ? $this->pdo()->lastInsertId() : null;
            $this->fireEvents(EventHandler::EVENT_AFTER_INSERT, $queryObject, [
                'insert_id'      => $insertId,
                'execution_time' => $executionTime,
            ]);

            return $insertId;
        }

        $insertIds = [];

        // If the current batch insert is not in a transaction, we create one...

        if ($this->pdo()->inTransaction() === false) {

            $this->transaction(function (Transaction $transaction) use (&$insertIds, $data, $type): void {
                foreach ($data as $subData) {
                    $insertIds[] = $transaction->doInsert($subData, $type);
                }
            });

            return $insertIds;
        }

        // Otherwise insert one by one...
        foreach ($data as $subData) {
            $insertIds[] = $this->doInsert($subData, $type);
        }

        return $insertIds;
    }

    /**
     * Performs the transaction
     *
     * @param \Closure $callback
     *
     * @return Transaction
     * @throws Exception
     */
    public function transaction(\Closure $callback): Transaction
    {
        $queryTransaction = new Transaction($this->connection);
        $queryTransaction->statements = $this->statements;

        try {
            // Begin the PDO transaction
            if ($this->pdo()->inTransaction() === false) {
                $this->pdo()->beginTransaction();
            }

            // Call closure - this callback will return TransactionHaltException if user has already committed the transaction
            $callback($queryTransaction);

            // If no errors have been thrown or the transaction wasn't completed within the closure, commit the changes
            $this->pdo()->commit();

        } catch (TransactionHaltException $e) {

            // Commit or rollback behavior has been triggered in the closure
            return $queryTransaction;

        } catch (\Exception $e) {

            // Something went wrong. Rollback and throw Exception
            if ($this->pdo()->inTransaction() === true) {
                $this->pdo()->rollBack();
            }

            throw Exception::create($e, $this->getConnection()->getAdapter()->getQueryAdapterClass(), $this->getLastQuery());
        }

        return $queryTransaction;
    }

    /**
     * @param string|Raw|\Closure $table
     * @param string|array $fields
     * @param string $joinType
     * @return static
     * @throws Exception
     */
    public function joinUsing($table, $fields, $joinType = ''): self
    {
        if (\is_array($fields) === false) {
            $fields = [$fields];
        }

        $joinBuilder = new JoinBuilder($this->connection);
        $joinBuilder->using($fields);

        $table = $this->addTablePrefix($table, false);

        $this->removeExistingStatement('joins', 'table', $table);

        $this->statements['joins'][] = [
            'type'        => $joinType,
            'table'       => $table,
            'joinBuilder' => $joinBuilder,
        ];

        return $this;
    }

    /**
     * Adds new LEFT JOIN statement to the current query.
     *
     * @param string|Raw|\Closure|array $table
     * @param string|JoinBuilder|Raw|\Closure $key
     * @param string|null $operator
     * @param string|Raw|\Closure|null $value
     *
     * @return static
     * @throws Exception
     */
    public function leftJoin($table, $key, $operator = null, $value = null): self
    {
        return $this->join($table, $key, $operator, $value, 'left');
    }

    /**
     * Adds OFFSET statement to the current query.
     *
     * @param int $offset
     *
     * @return static $this
     */
    public function offset(int $offset): self
    {
        $this->statements['offset'] = $offset;

        return $this;
    }

    /**
     * Add on duplicate key statement.
     *
     * @param array $data
     *
     * @return static
     */
    public function onDuplicateKeyUpdate(array $data): self
    {
        $this->addStatement('onduplicate', $data);

        return $this;
    }

    /**
     * Adds OR HAVING statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param string|Raw|\Closure $operator
     * @param mixed|Raw|\Closure|null $value
     *
     * @return static
     */
    public function orHaving($key, $operator, $value): self
    {
        return $this->having($key, $operator, $value, 'OR');
    }

    /**
     * Adds HAVING statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param string|mixed $operator
     * @param string|mixed $value
     * @param string $joiner
     *
     * @return static
     */
    public function having($key, $operator, $value, $joiner = 'AND'): self
    {
        $key = $this->addTablePrefix($key);
        $this->removeExistingStatement('havings', 'key', $key);
        $this->statements['havings'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * Adds OR WHERE statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param string|null $operator
     * @param mixed|Raw|\Closure|null $value
     *
     * @return static
     */
    public function orWhere($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (\func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR');
    }

    /**
     * Adds OR WHERE BETWEEN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param string|integer|float $valueFrom
     * @param string|integer|float $valueTo
     *
     * @return static
     */
    public function orWhereBetween($key, $valueFrom, $valueTo): self
    {
        return $this->whereHandler($key, 'BETWEEN', [$valueFrom, $valueTo], 'OR');
    }

    /**
     * Adds OR WHERE IN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param array|Raw|\Closure $values
     *
     * @return static
     */
    public function orWhereIn($key, $values): self
    {
        return $this->whereHandler($key, 'IN', $values, 'OR');
    }

    /**
     * Adds OR WHERE NOT statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param string|null $operator
     * @param mixed|Raw|\Closure|null $value
     *
     * @return static
     */
    public function orWhereNot($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (\func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR NOT');
    }

    /**
     * Adds or WHERE NOT IN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param array|Raw|\Closure $values
     *
     * @return static
     */
    public function orWhereNotIn($key, $values): self
    {
        return $this->whereHandler($key, 'NOT IN', $values, 'OR');
    }

    /**
     * Adds OR WHERE NOT NULL statement to the current query.
     *
     * @param string|Raw|\Closure $key
     *
     * @return static
     */
    public function orWhereNotNull($key): self
    {
        return $this->whereNullHandler($key, 'NOT', 'or');
    }

    /**
     * Handles WHERE NULL statements.
     *
     * @param string|Raw|\Closure $key
     * @param string $prefix
     * @param string $operator
     *
     * @return static
     */
    protected function whereNullHandler($key, string $prefix = '', string $operator = ''): self
    {
        $this->removeExistingStatement('wheres', 'key', $key);

        $prefix = 'IS' . (($prefix !== '') ? ' ' . $prefix : '');

        return $this->{$operator . 'Where'}($key, $prefix, $this->raw('NULL'));
    }

    /**
     * Adds OR WHERE NULL statement to the current query.
     *
     * @param string|Raw|\Closure $key
     *
     * @return static
     */
    public function orWhereNull($key): self
    {
        return $this->whereNullHandler($key, '', 'or');
    }

    /**
     * Adds ORDER BY statement to the current query.
     *
     * @param string|Raw|\Closure|array $fields
     * @param string $direction
     *
     * @return static
     */
    public function orderBy($fields, string $direction = 'ASC'): self
    {
        if (\is_array($fields) === false) {
            $fields = [$fields];
        }

        foreach ($fields as $key => $value) {
            $field = $key;
            $type = $value;

            if (\is_int($key) === true) {
                $field = $value;
                $type = $direction;
            }

            if (($field instanceof Raw) === false) {
                $field = $this->addTablePrefix($field);
            }

            $this->removeExistingStatement('orderBys', 'field', $field);
            $this->statements['orderBys'][] = compact('field', 'type');
        }

        return $this;
    }

    /**
     * Performs query.
     *
     * @param string $sql
     * @param array  $bindings
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return static
     */
    public function query(string $sql, array $bindings = []): self
    {
        $queryObject = new QueryObject($sql, $bindings, $this->getConnection());
        $this->connection->setLastQuery($queryObject);

        $this->fireEvents(EventHandler::EVENT_BEFORE_QUERY, $queryObject);

        [$response, $executionTime] = $this->statement($queryObject->getSql(), $queryObject->getBindings());

        $this->fireEvents(EventHandler::EVENT_AFTER_QUERY, $queryObject, [
            'execution_time' => $executionTime,
        ]);

        $this->pdoStatement = $response;

        return $this;
    }

    /**
     * Register new event
     *
     * @param string $name
     * @param \Closure $action
     * @param string $table
     *
     * @return void
     */
    public function registerEvent(string $name, \Closure $action, string $table = EventHandler::TABLE_ANY): void
    {
        $this->connection->getEventHandler()->registerEvent($name, $action, $table);
    }

    /**
     * Remove event by event-name and/or table
     *
     * @param string $name
     * @param string|null $table
     *
     * @return void
     */
    public function removeEvent(string $name, ?string $table = null): void
    {
        $this->connection->getEventHandler()->removeEvent($name, $table);
    }

    /**
     * Replace key/value array
     *
     * @param array $data
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return array|string
     * @throws Exception
     */
    public function replace(array $data)
    {
        return $this->doInsert($data, 'replace');
    }

    /**
     * Adds new right join statement to the current query.
     *
     * @param string|Raw|\Closure|array $table
     * @param string|JoinBuilder|Raw|\Closure $key
     * @param string|null $operator
     * @param string|Raw|\Closure|null $value
     *
     * @return static
     * @throws Exception
     */
    public function rightJoin($table, $key, ?string $operator = null, $value = null): self
    {
        return $this->join($table, $key, $operator, $value, 'right');
    }

    /**
     * Performs select distinct on the current query.
     *
     * @param string|Raw|\Closure|array $fields
     *
     * @return static
     */
    public function selectDistinct($fields): self
    {
        if ($this->overwriteEnabled === true) {
            $this->statements['distincts'] = $fields;
        } else {
            $this->addStatement('distincts', $fields);
        }

        return $this;
    }

    /**
     * Add union
     *
     * @param QueryBuilderHandler $query
     * @param string|null $unionType
     *
     * @return static $this
     */
    public function union(QueryBuilderHandler $query, ?string $unionType = self::UNION_TYPE_NONE): self
    {
        $statements = $query->getStatements();

        if (\count($statements['unions']) > 0) {
            $this->statements['unions'] = $statements['unions'];
            unset($statements['unions']);
            $query->setStatements($statements);
        }

        $this->statements['unions'][] = [
            'query' => $query,
            'type'  => $unionType,
        ];

        return $this;
    }

    /**
     * Returns statements
     *
     * @return array
     */
    public function getStatements(): array
    {
        return $this->statements;
    }

    /**
     * @param array $statements
     *
     * @return static $this
     */
    public function setStatements(array $statements): self
    {
        $this->statements = $statements;

        return $this;
    }

    /**
     * Update or insert key/value array
     *
     * @param array $data
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return array|\PDOStatement|string
     */
    public function updateOrInsert(array $data)
    {
        if ($this->first() !== null) {
            return $this->update($data);
        }

        return $this->insert($data);
    }

    /**
     * Update key/value array
     *
     * @param array $data
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return \PDOStatement
     * @throws Exception
     */
    public function update(array $data): \PDOStatement
    {
        $queryObject = $this->getQuery('update', $data);

        $this->connection->setLastQuery($queryObject);

        $this->fireEvents(EventHandler::EVENT_BEFORE_UPDATE, $queryObject);

        /** @var \PDOStatement $response */
        [$response, $executionTime] = $this->statement($queryObject->getSql(), $queryObject->getBindings());

        $this->fireEvents(EventHandler::EVENT_AFTER_UPDATE, $queryObject, [
            'execution_time' => $executionTime,
        ]);

        return $response;
    }

    /**
     * Insert key/value array
     *
     * @param array $data
     *
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @return array|string
     * @throws Exception
     */
    public function insert(array $data)
    {
        return $this->doInsert($data, 'insert');
    }

    /**
     * Adds WHERE BETWEEN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param string|integer|float|Raw|\Closure $valueFrom
     * @param string|integer|float|Raw|\Closure $valueTo
     *
     * @return static
     */
    public function whereBetween($key, $valueFrom, $valueTo): self
    {
        return $this->whereHandler($key, 'BETWEEN', [$valueFrom, $valueTo]);
    }

    /**
     * Adds WHERE IN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param array|Raw|\Closure $values
     *
     * @return static
     */
    public function whereIn($key, $values): self
    {
        return $this->whereHandler($key, 'IN', $values);
    }

    /**
     * Adds WHERE NOT statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param string|array|Raw|\Closure|null $operator
     * @param mixed|Raw|\Closure|null $value
     *
     * @return static
     */
    public function whereNot($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (\func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'AND NOT');
    }

    /**
     * Adds OR WHERE NOT IN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param array|Raw|\Closure $values
     *
     * @return static
     */
    public function whereNotIn($key, $values): self
    {
        return $this->whereHandler($key, 'NOT IN', $values);
    }

    /**
     * Adds WHERE NOT NULL statement to the current query.
     *
     * @param string|Raw|\Closure $key
     *
     * @return static
     */
    public function whereNotNull($key): self
    {
        return $this->whereNullHandler($key, 'NOT');
    }

    /**
     * Adds WHERE NULL statement to the current query.
     *
     * @param string|Raw|\Closure $key
     *
     * @return static
     */
    public function whereNull($key): self
    {
        return $this->whereNullHandler($key);
    }

    /**
     * Will add FOR statement to the end of the SELECT statement, like FOR UPDATE, FOR SHARE etc.
     * @param string $statement
     * @return static
     */
    public function for(string $statement): self
    {
        $this->addStatement('for', $statement);

        return $this;
    }

    /**
     * Returns all columns in current query
     *
     * @return array
     */
    public function getColumns(): array
    {
        $tSelects = isset($this->statements['selects']) === true ? $this->statements['selects'] : [];
        $tColumns = [];
        foreach ($tSelects as $key => $value) {
            if (\is_string($value)) {
                if (\is_int($key)) {
                    $tElements = explode('.', $value);
                    if (!\in_array('*', $tElements, true)) {
                        $tColumns[$tElements[1] ?? $tElements[0]] = $value;
                    }
                } elseif (\is_string($key)) {
                    $tColumns[$value] = $key;
                }
            }
        }

        return $tColumns;
    }

    /**
     * Returns boolean value indicating if overwriting is enabled or disabled in QueryBuilderHandler.
     *
     * @return bool
     */
    public function isOverwriteEnabled(): bool
    {
        return $this->overwriteEnabled;
    }

    /**
     * If enabled calling from, select etc. will overwrite any existing values from previous calls in query.
     *
     * @param bool $enabled
     * @return static
     */
    public function setOverwriteEnabled(bool $enabled): self
    {
        $this->overwriteEnabled = $enabled;

        return $this;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Close connection
     */
    public function close(): void
    {
        $this->pdoStatement = null;
        $this->connection = null;
    }

}