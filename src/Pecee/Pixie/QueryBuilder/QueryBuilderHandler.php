<?php

namespace Pecee\Pixie\QueryBuilder;

use PDO;
use Pecee\Pixie\Connection;
use Pecee\Pixie\Exception;

/**
 * Class QueryBuilderHandler
 *
 * @package Pecee\Pixie\QueryBuilder
 */
class QueryBuilderHandler
{

    /**
     * @var \Viocon\Container
     */
    protected $container;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $statements = [
        'groupBys' => [],
    ];

    /**
     * @var \PDO
     */
    protected $pdo;

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
    protected $fetchParameters = [\PDO::FETCH_OBJ];

    /**
     * @var string
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $adapterConfig;

    /**
     * @param \Pecee\Pixie\Connection|null $connection
     *
     * @throws \Pecee\Pixie\Exception
     */
    public function __construct(Connection $connection = null)
    {
        $this->connection = $connection ?? Connection::getStoredConnection();

        if (is_null($this->connection)) {
            throw new Exception('No database connection found.', 1);
        }

        $this->container     = $this->connection->getContainer();
        $this->pdo           = $this->connection->getPdoInstance();
        $this->adapter       = $this->connection->getAdapter();
        $this->adapterConfig = $this->connection->getAdapterConfig();

        if (isset($this->adapterConfig['prefix']) === true) {
            $this->tablePrefix = $this->adapterConfig['prefix'];
        }

        // Query builder adapter instance
        $this->adapterInstance = $this->container->build(
            '\Pecee\Pixie\QueryBuilder\Adapters\\' . ucfirst($this->adapter),
            [$this->connection]
        );

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Add new statement to statement-list
     *
     * @param string $key
     * @param mixed  $value
     */
    protected function addStatement(string $key, $value)
    {
        if (array_key_exists($key, $this->statements) === false) {
            $this->statements[$key] = (array)$value;
        } else {
            $this->statements[$key] = array_merge($this->statements[$key], (array)$value);
        }
    }

    /**
     * Add table prefix (if given) on given string.
     *
     * @param string|array|Raw|\Closure $values
     * @param bool                      $tableFieldMix If we have mixes of field and table names with a "."
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
        if (is_array($values) === false) {
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

            if (is_int($key) === false) {
                $target = &$key;
            }

            if ($tableFieldMix === false || ($tableFieldMix && strpos($target, '.') !== false)) {
                $target = $this->tablePrefix . $target;
            }

            $return[$key] = $value;
        }

        // If we had single value then we should return a single value (end value of the array)
        return $single ? end($return) : $return;
    }

    /**
     * Performs special queries like COUNT, SUM etc based on the current query.
     *
     * @param string $type
     *
     * @throws Exception
     * @return int
     */
    protected function aggregate(string $type)
    {
        // Get the current selects
        $mainSelects = isset($this->statements['selects']) ? $this->statements['selects'] : null;

        // Replace select with a scalar value like `count`
        $this->statements['selects'] = [$this->raw($type . '(*) AS `field`')];
        $row                         = $this->get();

        // Set the select as it was
        if ($mainSelects !== null) {
            $this->statements['selects'] = $mainSelects;
        } else {
            unset($this->statements['selects']);
        }

        if (isset($row[0]) === true) {
            if (is_array($row[0]) === true) {
                return (int)$row[0]['field'];
            }
            if (is_object($row[0]) === true) {
                return (int)$row[0]->field;
            }
        }

        return 0;
    }

    /**
     * Add or change table alias
     * Example: table AS alias
     *
     * @param string $table
     * @param string $alias
     *
     * @return static
     */
    public function alias(string $table, string $alias)
    {
        $this->statements['aliases'][$this->tablePrefix . $table] = strtolower($alias);

        return $this;
    }

    /**
     * Fetch query results as object of specified type
     *
     * @param string $className
     * @param array  $constructorArgs
     *
     * @return static
     */
    public function asObject(string $className, array $constructorArgs = []): QueryBuilderHandler
    {
        return $this->setFetchMode(\PDO::FETCH_CLASS, $className, $constructorArgs);
    }

    /**
     * Get count of rows
     *
     * @throws Exception
     * @return int
     */
    public function count(): int
    {
        // Get the current statements
        $originalStatements = $this->statements;

        unset($this->statements['orderBys'], $this->statements['limit'], $this->statements['offset']);

        $count            = $this->aggregate('count');
        $this->statements = $originalStatements;

        return $count;
    }

    /**
     * Forms delete on the current query.
     *
     * @return \PDOStatement
     * @throws Exception
     */
    public function delete(): \PDOStatement
    {
        /* @var $response \PDOStatement */
        $queryObject = $this->getQuery('delete');

        $this->fireEvents('before-delete', $queryObject);

        list($response, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
        $this->fireEvents('after-delete', $queryObject, $executionTime);

        return $response;
    }

    /**
     * Performs insert
     *
     * @param array  $data
     * @param string $type
     *
     * @throws Exception
     * @return array|string|null
     */
    private function doInsert(array $data, string $type)
    {
        // If first value is not an array - it's not a batch insert
        if (is_array(current($data)) === false) {
            $queryObject = $this->getQuery($type, $data);

            $this->fireEvents('before-insert', $queryObject);
            /**
             * @var $result        \PDOStatement
             * @var $executionTime float
             */
            list($result, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
            $return = $result->rowCount() === 1 ? $this->pdo->lastInsertId() : null;
            $this->fireEvents('after-insert', $queryObject, $return, $executionTime);

            return $return;
        }

        // Perform batch insert

        $return = [];
        foreach ($data as $subData) {
            $queryObject = $this->getQuery($type, $subData);

            $this->fireEvents('before-insert', $queryObject);
            list($result, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
            $result = $result->rowCount() === 1 ? $this->pdo->lastInsertId() : null;
            $this->fireEvents('after-insert', $queryObject, $result, $executionTime);

            $return[] = $result;
        }

        return $return;
    }

    /**
     * Find by value and field name.
     *
     * @param string|int|float $value
     * @param string           $fieldName
     *
     * @throws Exception
     * @return null|\stdClass
     */
    public function find($value, $fieldName = 'id')
    {
        return $this->where($fieldName, '=', $value)->first();
    }

    /**
     * Find all by field name and value
     *
     * @param string           $fieldName
     * @param string|int|float $value
     *
     * @throws Exception
     * @return \stdClass[]
     */
    public function findAll(string $fieldName, $value)
    {
        return $this->where($fieldName, '=', $value)->get();
    }

    /**
     * Fires event by given event name
     *
     * @param string $name
     * @param ... $parameters
     *
     * @return mixed|null
     */
    public function fireEvents($name, $parameters = null)
    {
        $params = func_get_args();
        array_unshift($params, $this);

        return call_user_func_array([$this->connection->getEventHandler(), 'fireEvents'], $params);
    }

    /**
     * Returns the first row
     *
     * @throws Exception
     * @return \stdClass|null
     */
    public function first()
    {
        $result = $this->limit(1)->get();

        return ($result !== null && count($result) > 0) ? $result[0] : null;
    }

    /**
     * Adds FROM statement to the current query.
     *
     * @param string|array $tables
     *
     * @return static
     */
    public function from($tables)
    {
        if (is_array($tables) === false) {
            $tables = func_get_args();
        }

        $tables = $this->addTablePrefix($tables, false);
        $this->addStatement('tables', $tables);

        return $this;
    }

    /**
     * Get all rows
     *
     * @throws Exception
     * @return object[]
     */
    public function get(): array
    {
        /**
         * @var $queryObject   \Pecee\Pixie\QueryBuilder\QueryObject
         * @var $executionTime float
         * @var $start         float
         * @var $result        array
         */
        $queryObject   = null;
        $executionTime = 0;

        if ($this->pdoStatement === null) {
            $queryObject = $this->getQuery();
            list($this->pdoStatement, $executionTime) = $this->statement(
                $queryObject->getSql(),
                $queryObject->getBindings()
            );
        }

        $start = microtime(true);
        $this->fireEvents('before-select', $queryObject);
        $result             = call_user_func_array([$this->pdoStatement, 'fetchAll'], $this->fetchParameters);
        $executionTime      += microtime(true) - $start;
        $this->pdoStatement = null;
        $this->fireEvents('after-select', $queryObject, $result, $executionTime);

        return $result;
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
     * Get event by event name
     *
     * @param string      $name
     * @param string|null $table
     *
     * @return \Closure|null
     */
    public function getEvent(string $name, string $table = null)
    {
        return $this->connection->getEventHandler()->getEvent($name, $table);
    }

    /**
     * Returns Query-object.
     *
     * @param string           $type
     * @param array|mixed|null $dataToBePassed
     *
     * @return QueryObject
     * @throws Exception
     */
    public function getQuery(string $type = 'select', $dataToBePassed = null): QueryObject
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

        if (in_array(strtolower($type), $allowedTypes, true) === false) {
            throw new Exception($type . ' is not a known type.', 2);
        }

        $queryArr = $this->adapterInstance->$type($this->statements, $dataToBePassed);

        return $this->container->build(
            QueryObject::class,
            [$queryArr['sql'], $queryArr['bindings'], $this->pdo]
        );
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
     * Adds GROUP BY to the current query.
     *
     * @param string|Raw|\Closure|array $field
     *
     * @return static
     */
    public function groupBy($field)
    {
        if (($field instanceof Raw) === false) {
            $field = $this->addTablePrefix($field);
        }

        if (is_array($field) === true) {
            $this->statements['groupBys'] = array_merge($this->statements['groupBys'], $field);
        } else {
            $this->statements['groupBys'][] = $field;
        }

        return $this;
    }

    /**
     * Adds HAVING statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param string|mixed        $operator
     * @param string|mixed        $value
     * @param string              $joiner
     *
     * @return static
     */
    public function having($key, $operator, $value, $joiner = 'AND')
    {
        $key                           = $this->addTablePrefix($key);
        $this->statements['havings'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * Adds new INNER JOIN statement to the current query.
     *
     * @param string|Raw|\Closure      $table
     * @param string|Raw|\Closure      $key
     * @param string|mixed|null        $operator
     * @param string|Raw|\Closure|null $value
     *
     * @return static
     */
    public function innerJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value);
    }

    /**
     * Insert key/value array
     *
     * @param array $data
     *
     * @throws Exception
     * @return array|string
     */
    public function insert(array $data)
    {
        return $this->doInsert($data, 'insert');
    }

    /**
     * Insert with ignore key/value array
     *
     * @param array $data
     *
     * @throws Exception
     * @return array|string
     */
    public function insertIgnore($data)
    {
        return $this->doInsert($data, 'insertignore');
    }

    /**
     * Adds new JOIN statement to the current query.
     *
     * @param string|Raw|\Closure|array $table
     * @param string|Raw|\Closure       $key
     * @param string|null               $operator
     * @param string|Raw|\Closure       $value
     * @param string                    $type
     *
     * @return static
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
    public function join($table, $key, $operator = null, $value = null, $type = 'inner')
    {
        if (($key instanceof \Closure) === false) {
            $key = function (JoinBuilder $joinBuilder) use ($key, $operator, $value) {
                $joinBuilder->on($key, $operator, $value);
            };
        }

        /**
         * Build a new JoinBuilder class, keep it by reference so any changes made
         * in the closure should reflect here
         */

        $joinBuilder = $this->container->build(JoinBuilder::class, [$this->connection]);

        // Call the closure with our new joinBuilder object
        $key($joinBuilder);
        $table = $this->addTablePrefix($table, false);

        // Get the criteria only query from the joinBuilder object
        $this->statements['joins'][] = compact('type', 'table', 'joinBuilder');

        return $this;
    }

    /**
     * Adds new LEFT JOIN statement to the current query.
     *
     * @param string|Raw|\Closure|array $table
     * @param string|Raw|\Closure       $key
     * @param string|null               $operator
     * @param string|Raw|\Closure|null  $value
     *
     * @return static
     */
    public function leftJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'left');
    }

    /**
     * Adds LIMIT statement to the current query.
     *
     * @param int $limit
     *
     * @return static
     */
    public function limit($limit)
    {
        $this->statements['limit'] = $limit;

        return $this;
    }

    /**
     * Creates and returns new query.
     *
     * @param \Pecee\Pixie\Connection|null $connection
     *
     * @throws \Pecee\Pixie\Exception
     * @return static
     */
    public function newQuery(Connection $connection = null)
    {
        if ($connection === null) {
            $connection = $this->connection;
        }

        return new static($connection);
    }

    /**
     * Adds OFFSET statement to the current query.
     *
     * @param int $offset
     *
     * @return static $this
     */
    public function offset($offset)
    {
        $this->statements['offset'] = $offset;

        return $this;
    }

    /**
     * Add on duplicate key statement.
     *
     * @param string $data
     *
     * @return static
     */
    public function onDuplicateKeyUpdate($data)
    {
        $this->addStatement('onduplicate', $data);

        return $this;
    }

    /**
     * Adds OR HAVING statement to the current query.
     *
     * @param string|Raw|\Closure     $key
     * @param string|Raw|\Closure     $operator
     * @param mixed|Raw|\Closure|null $value
     *
     * @return static
     */
    public function orHaving($key, $operator, $value)
    {
        return $this->having($key, $operator, $value, 'OR');
    }

    /**
     * Adds OR WHERE statement to the current query.
     *
     * @param string|Raw|\Closure     $key
     * @param string|null             $operator
     * @param mixed|Raw|\Closure|null $value
     *
     * @return static
     */
    public function orWhere($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR');
    }

    /**
     * Adds OR WHERE BETWEEN statement to the current query.
     *
     * @param string|Raw|\Closure  $key
     * @param string|integer|float $valueFrom
     * @param string|integer|float $valueTo
     *
     * @return static
     */
    public function orWhereBetween($key, $valueFrom, $valueTo)
    {
        return $this->whereHandler($key, 'BETWEEN', [$valueFrom, $valueTo], 'OR');
    }

    /**
     * Adds OR WHERE IN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param array|Raw|\Closure  $values
     *
     * @return static
     */
    public function orWhereIn($key, $values)
    {
        return $this->whereHandler($key, 'IN', $values, 'OR');
    }

    /**
     * Adds OR WHERE NOT statement to the current query.
     *
     * @param string|Raw|\Closure            $key
     * @param string|array|Raw|\Closure|null $operator
     * @param mixed|Raw|\Closure|null        $value
     *
     * @return static
     */
    public function orWhereNot($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR NOT');
    }

    /**
     * Adds or WHERE NOT IN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param array|Raw|\Closure  $values
     *
     * @return static
     */
    public function orWhereNotIn($key, $values)
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
    public function orWhereNotNull($key)
    {
        return $this->whereNullHandler($key, 'NOT', 'or');
    }

    /**
     * Adds OR WHERE NULL statement to the current query.
     *
     * @param string|Raw|\Closure $key
     *
     * @return static
     */
    public function orWhereNull($key)
    {
        return $this->whereNullHandler($key, '', 'or');
    }

    /**
     * Adds ORDER BY statement to the current query.
     *
     * @param string|Raw|\Closure|array $fields
     * @param string                    $defaultDirection
     *
     * @return static
     */
    public function orderBy($fields, $defaultDirection = 'ASC')
    {
        if (is_array($fields) === false) {
            $fields = [$fields];
        }

        foreach ((array)$fields as $key => $value) {
            $field = $key;
            $type  = $value;

            if (is_int($key) === true) {
                $field = $value;
                $type  = $defaultDirection;
            }

            if (($field instanceof Raw) === false) {
                $field = $this->addTablePrefix($field);
            }

            $this->statements['orderBys'][] = compact('field', 'type');
        }

        return $this;
    }

    /**
     * Parses correct data-type for PDO parameter.
     *
     * @param mixed $value
     *
     * @return int PDO-parameter type
     */
    protected function parseDataType($value)
    {
        if (is_int($value) === true) {
            return PDO::PARAM_INT;
        }

        if (is_bool($value) === true) {
            return PDO::PARAM_BOOL;
        }

        return PDO::PARAM_STR;
    }

    /**
     * Return PDO instance
     *
     * @return PDO
     */
    public function pdo()
    {
        return $this->pdo;
    }

    /**
     * Add or change table alias
     *
     * Example: table AS alias
     *
     * @deprecated This method will be removed in the near future, please use QueryBuilderHandler::alias instead.
     * @see        QueryBuilderHandler::alias
     *
     * @param string $table
     * @param string $alias
     *
     * @return static
     */
    public function prefix($table, $alias)
    {
        return $this->alias($table, $alias);
    }

    /**
     * Performs query.
     *
     * @param string $sql
     * @param array  $bindings
     *
     * @return static
     */
    public function query($sql, array $bindings = [])
    {
        list($this->pdoStatement) = $this->statement($sql, $bindings);

        return $this;
    }

    /**
     * Adds a raw string to the current query.
     * This query will be ignored from any parsing or formatting by the Query builder
     * and should be used in conjunction with other statements in the query.
     *
     * For example: $qb->where('result', '>', $qb->raw('COUNT(`score`)));
     *
     * @param string           $value
     * @param array|null|mixed $bindings ...
     *
     * @return Raw
     */
    public function raw($value, $bindings = null)
    {
        if (is_array($bindings) === false) {
            $bindings = func_get_args();
            array_shift($bindings);
        }

        return $this->container->build(Raw::class, [$value, $bindings]);
    }

    /**
     * Register new event
     *
     * @param string      $name
     * @param string|null $table
     * @param \Closure    $action
     *
     * @return void
     */
    public function registerEvent($name, $table = null, \Closure $action)
    {
        $this->connection->getEventHandler()->registerEvent($name, $table, $action);
    }

    /**
     * Remove event by event-name and/or table
     *
     * @param string      $name
     * @param string|null $table
     *
     * @return void
     */
    public function removeEvent($name, $table = null)
    {
        $this->connection->getEventHandler()->removeEvent($name, $table);
    }

    /**
     * Replace key/value array
     *
     * @param array $data
     *
     * @throws Exception
     * @return array|string
     */
    public function replace($data)
    {
        return $this->doInsert($data, 'replace');
    }

    /**
     * Adds new right join statement to the current query.
     *
     * @param string|Raw|\Closure|array $table
     * @param string|Raw|\Closure       $key
     * @param string|null               $operator
     * @param string|Raw|\Closure|null  $value
     *
     * @return static
     */
    public function rightJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'right');
    }

    /**
     * Adds fields to select on the current query (defaults is all).
     * You can use key/value array to create alias.
     * Sub-queries and raw-objects are also supported.
     *
     * Example: ['field' => 'alias'] will become `field` AS `alias`
     *
     * @param string|array $fields,...
     *
     * @return static
     */
    public function select($fields)
    {
        if (is_array($fields) === false) {
            $fields = func_get_args();
        }

        $fields = $this->addTablePrefix($fields);
        $this->addStatement('selects', $fields);

        return $this;
    }

    /**
     * Performs select distinct on the current query.
     *
     * @param string|Raw|\Closure|array $fields
     *
     * @return static
     */
    public function selectDistinct($fields)
    {
        $this->select($fields);
        $this->addStatement('distinct', true);

        return $this;
    }

    /**
     * Set connection object
     *
     * @param Connection $connection
     *
     * @return static
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Add fetch parameters to the PDO-query.
     *
     * @param mixed $parameters ...
     *
     * @return static
     */
    public function setFetchMode($parameters = null)
    {
        $this->fetchParameters = func_get_args();

        return $this;
    }

    /**
     * Execute statement
     *
     * @param string $sql
     * @param array  $bindings
     *
     * @return array PDOStatement and execution time as float
     */
    public function statement(string $sql, array $bindings = [])
    {
        $start = microtime(true);

        $pdoStatement = $this->pdo->prepare($sql);

        foreach ($bindings as $key => $value) {
            $pdoStatement->bindValue(
                is_int($key) ? $key + 1 : $key,
                $value,
                $this->parseDataType($value)
            );
        }

        $pdoStatement->execute();

        return [$pdoStatement, microtime(true) - $start];
    }

    /**
     * Performs new sub-query.
     * Call this method when you want to add a new sub-query in your where etc.
     *
     * @param QueryBuilderHandler $queryBuilder
     * @param string|null         $alias
     *
     * @throws Exception
     * @return Raw
     */
    public function subQuery(QueryBuilderHandler $queryBuilder, $alias = null)
    {
        $sql = '(' . $queryBuilder->getQuery()->getRawSql() . ')';
        if ($alias !== null) {
            $sql = $sql . ' AS ' . $this->adapterInstance->wrapSanitizer($alias);
        }

        return $queryBuilder->raw($sql);
    }

    /**
     * Sets the table that the query is using
     *
     * @param string|array $tables Single table or multiple tables as an array or as multiple parameters
     *
     * @throws Exception
     * @return static
     */
    public function table($tables)
    {
        $tTables = [];
        if (is_array($tables) === false) {
            // Because a single table is converted to an array anyways, this makes sense.
            $tables = func_get_args();
        }

        $instance = new static($this->connection);
        foreach ($tables as $key => $value) {
            if (is_string($key)) {
                $instance->alias($key, $value);
                $tTables[] = $key;
            } else {
                $tTables[] = $value;
            }
        }
        $tables = $this->addTablePrefix($tTables, false);
        $instance->addStatement('tables', $tTables);

        return $instance;
    }

    /**
     * Performs the transaction
     *
     * @param \Closure $callback
     *
     * @return static
     */
    public function transaction(\Closure $callback)
    {
        try {
            // Begin the PDO transaction
            $this->pdo->beginTransaction();

            // Get the Transaction class
            $transaction = $this->container->build(Transaction::class, [$this->connection]);

            // Call closure
            $callback($transaction);

            // If no errors have been thrown or the transaction wasn't completed within the closure, commit the changes
            $this->pdo->commit();

            return $this;
        } catch (TransactionHaltException $e) {
            // Commit or rollback behavior has been handled in the closure, so exit
            return $this;
        } catch (\Exception $e) {
            // something happened, rollback changes
            $this->pdo->rollBack();

            return $this;
        }
    }

    /**
     * Update key/value array
     *
     * @param array $data
     *
     * @throws Exception
     * @return \PDOStatement
     */
    public function update($data)
    {
        /**
         * @var $response \PDOStatement
         */
        $queryObject = $this->getQuery('update', $data);

        $this->fireEvents('before-update', $queryObject);

        list($response, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
        $this->fireEvents('after-update', $queryObject, $executionTime);

        return $response;
    }

    /**
     * Update or insert key/value array
     *
     * @param array $data
     *
     * @return array|\PDOStatement|string
     * @throws Exception
     */
    public function updateOrInsert($data)
    {
        if ($this->first() !== null) {
            return $this->update($data);
        }

        return $this->insert($data);
    }

    /**
     * Adds WHERE statement to the current query.
     *
     * @param string|Raw|\Closure      $key
     * @param string|Raw|\Closure|null $operator
     * @param mixed|Raw|\Closure|null  $value
     *
     * @return static
     */
    public function where($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }

        if (is_bool($value) === true) {
            $value = (int)$value;
        }

        return $this->whereHandler($key, $operator, $value);
    }

    /**
     * Adds WHERE BETWEEN statement to the current query.
     *
     * @param string|Raw|\Closure  $key
     * @param string|integer|float $valueFrom
     * @param string|integer|float $valueTo
     *
     * @return static
     */
    public function whereBetween($key, $valueFrom, $valueTo)
    {
        return $this->whereHandler($key, 'BETWEEN', [$valueFrom, $valueTo]);
    }

    /**
     * Handles where statements
     *
     * @param string|Raw|\Closure      $key
     * @param string|Raw|\Closure|null $operator
     * @param string|Raw|\Closure|null $value
     * @param string                   $joiner
     *
     * @return static
     */
    protected function whereHandler($key, $operator = null, $value = null, $joiner = 'AND')
    {
        $key                          = $this->addTablePrefix($key);
        $this->statements['wheres'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * Adds WHERE IN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param array|Raw|\Closure  $values
     *
     * @return static
     */
    public function whereIn($key, $values)
    {
        return $this->whereHandler($key, 'IN', $values);
    }

    /**
     * Adds WHERE NOT statement to the current query.
     *
     * @param string|Raw|\Closure            $key
     * @param string|array|Raw|\Closure|null $operator
     * @param mixed|Raw|\Closure|null        $value
     *
     * @return static
     */
    public function whereNot($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'AND NOT');
    }

    /**
     * Adds OR WHERE NOT IN statement to the current query.
     *
     * @param string|Raw|\Closure $key
     * @param array|Raw|\Closure  $values
     *
     * @return static
     */
    public function whereNotIn($key, $values)
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
    public function whereNotNull($key)
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
    public function whereNull($key)
    {
        return $this->whereNullHandler($key);
    }

    /**
     * Handles WHERE NULL statements.
     *
     * @param string|Raw|\Closure $key
     * @param string              $prefix
     * @param string              $operator
     *
     * @return mixed
     */
    protected function whereNullHandler($key, $prefix = '', $operator = '')
    {
        $key = $this->adapterInstance->wrapSanitizer($this->addTablePrefix($key));

        return $this->{$operator . 'Where'}($this->raw("{$key} IS {$prefix} NULL"));
    }
}
