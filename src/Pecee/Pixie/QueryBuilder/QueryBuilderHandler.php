<?php

namespace Pecee\Pixie\QueryBuilder;

use PDO;
use Pecee\Pixie\Connection;
use Pecee\Pixie\Exception;

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
    protected $statements = [];

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
     * @param \Pecee\Pixie\Connection|null $connection
     *
     * @throws \Pecee\Pixie\Exception
     */
    public function __construct(Connection $connection = null)
    {
        if ($connection === null && ($connection = Connection::getStoredConnection()) === false) {
            throw new Exception('No database connection found.', 1);
        }

        $this->connection = $connection;
        $this->container = $this->connection->getContainer();
        $this->pdo = $this->connection->getPdoInstance();
        $this->adapter = $this->connection->getAdapter();
        $this->adapterConfig = $this->connection->getAdapterConfig();

        if (isset($this->adapterConfig['prefix'])) {
            $this->tablePrefix = $this->adapterConfig['prefix'];
        }

        // Query builder adapter instance
        $this->adapterInstance = $this->container->build(
            '\Pecee\Pixie\QueryBuilder\Adapters\\' . ucfirst($this->adapter),
            [$this->connection]
        );

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Set the fetch mode
     *
     * @param string $mode
     * @return static
     */
    public function setFetchMode($mode)
    {
        $this->fetchParameters = func_get_args();

        return $this;
    }

    /**
     * Fetch query results as object of specified type
     *
     * @param $className
     * @param array $constructorArgs
     * @return QueryBuilderHandler
     */
    public function asObject($className, array $constructorArgs = [])
    {
        return $this->setFetchMode(\PDO::FETCH_CLASS, $className, $constructorArgs);
    }

    /**
     * @param \Pecee\Pixie\Connection|null $connection
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
     * @param string $sql
     * @param array $bindings
     *
     * @return static
     */
    public function query($sql, array $bindings = [])
    {
        list($this->pdoStatement) = $this->statement($sql, $bindings);

        return $this;
    }

    /**
     * Add or change table alias
     * Example: table AS alias
     *
     * @param string $table
     * @param string $alias
     * @return QueryBuilderHandler
     */
    public function alias($table, $alias)
    {
        $this->statements['aliases'][$this->tablePrefix . $table] = strtolower($alias);

        return $this;
    }

    /**
     * Add or change table alias
     * Example: table AS alias
     *
     * @deprecated This method will be removed in the near future, please use QueryBuilderHandler::alias instead.
     * @see QueryBuilderHandler::alias
     * @param string $table
     * @param string $alias
     * @return QueryBuilderHandler
     */
    public function prefix($table, $alias)
    {
        return $this->alias($table, $alias);
    }

    /**
     * @param       $sql
     * @param array $bindings
     *
     * @return array PDOStatement and execution time as float
     */
    public function statement($sql, $bindings = [])
    {
        $start = microtime(true);
        $pdoStatement = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) {
            $pdoStatement->bindValue(
                is_int($key) ? $key + 1 : $key,
                $value,
                is_int($value) || is_bool($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
        $pdoStatement->execute();

        return [$pdoStatement, microtime(true) - $start];
    }

    /**
     * Get all rows
     * @throws Exception
     * @return \stdClass|array|null
     */
    public function get()
    {
        $queryObject = null;
        $executionTime = 0;
        if ($this->pdoStatement === null) {
            $queryObject = $this->getQuery('select');
            list($this->pdoStatement, $executionTime) = $this->statement(
                $queryObject->getSql(),
                $queryObject->getBindings()
            );
        }

        $start = microtime(true);
        $this->fireEvents('before-select', $queryObject);
        $result = call_user_func_array([$this->pdoStatement, 'fetchAll'], $this->fetchParameters);
        $executionTime += microtime(true) - $start;
        $this->pdoStatement = null;
        $this->fireEvents('after-select', $queryObject, $result, $executionTime);

        return $result;
    }

    /**
     * Get first row
     * @throws Exception
     * @return \stdClass|null
     */
    public function first()
    {
        $this->limit(1);
        $result = $this->get();

        return empty($result) ? null : $result[0];
    }

    /**
     * @param        $value
     * @param string $fieldName
     * @throws Exception
     * @return null|\stdClass
     */
    public function findAll($fieldName, $value)
    {
        $this->where($fieldName, '=', $value);

        return $this->get();
    }

    /**
     * @param        $value
     * @param string $fieldName
     * @throws Exception
     * @return null|\stdClass
     */
    public function find($value, $fieldName = 'id')
    {
        $this->where($fieldName, '=', $value);

        return $this->first();
    }

    /**
     * Get count of rows
     * @throws Exception
     * @return int
     */
    public function count()
    {
        // Get the current statements
        $originalStatements = $this->statements;

        unset($this->statements['orderBys'], $this->statements['limit'], $this->statements['offset']);

        $count = $this->aggregate('count');
        $this->statements = $originalStatements;

        return $count;
    }

    /**
     * @param $type
     * @throws Exception
     * @return int
     */
    protected function aggregate($type)
    {
        // Get the current selects
        $mainSelects = isset($this->statements['selects']) ? $this->statements['selects'] : null;
        // Replace select with a scalar value like `count`
        $this->statements['selects'] = [$this->raw($type . '(*) as field')];
        $row = $this->get();

        // Set the select as it was
        if ($mainSelects) {
            $this->statements['selects'] = $mainSelects;
        } else {
            unset($this->statements['selects']);
        }

        if (isset($row[0])) {
            if (is_array($row[0])) {
                return (int)$row[0]['field'];
            }
            if (is_object($row[0])) {
                return (int)$row[0]->field;
            }
        }

        return 0;
    }

    /**
     * @param string $type
     * @param array|bool $dataToBePassed
     *
     * @return QueryObject
     * @throws Exception
     */
    public function getQuery($type = 'select', $dataToBePassed = [])
    {
        $allowedTypes = ['select', 'insert', 'insertignore', 'replace', 'delete', 'update', 'criteriaonly'];
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
     * @param QueryBuilderHandler $queryBuilder
     * @param null $alias
     * @throws Exception
     * @return Raw
     */
    public function subQuery(QueryBuilderHandler $queryBuilder, $alias = null)
    {
        $sql = '(' . $queryBuilder->getQuery()->getRawSql() . ')';
        if ($alias) {
            $sql = $sql . ' as ' . $alias;
        }

        return $queryBuilder->raw($sql);
    }

    /**
     * @param array $data
     * @param string $type
     * @throws Exception
     * @return array|string
     */
    private function doInsert($data, $type)
    {
        $queryObject = null;

        // If first value is not an array - it's not a batch insert
        if (is_array(current($data)) === false) {
            $queryObject = $this->getQuery($type, $data);

            $this->fireEvents('before-insert', $queryObject);
            list($result, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
            $return = $result->rowCount() === 1 ? $this->pdo->lastInsertId() : null;
            $this->fireEvents('after-insert', $queryObject, $return, $executionTime);

        } else {
            // Its a batch insert
            $return = [];
            foreach ($data as $subData) {
                $queryObject = $this->getQuery($type, $subData);

                $this->fireEvents('before-insert', $queryObject);
                list($result, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
                $result = $result->rowCount() === 1 ? $this->pdo->lastInsertId() : null;
                $this->fireEvents('after-insert', $queryObject, $result, $executionTime);

                $return[] = $result;
            }
        }

        return $return;
    }

    /**
     * @param $data
     * @throws Exception
     * @return array|string
     */
    public function insert($data)
    {
        return $this->doInsert($data, 'insert');
    }

    /**
     * @param $data
     * @throws Exception
     * @return array|string
     */
    public function insertIgnore($data)
    {
        return $this->doInsert($data, 'insertignore');
    }

    /**
     * @param $data
     * @throws Exception
     * @return array|string
     */
    public function replace($data)
    {
        return $this->doInsert($data, 'replace');
    }

    /**
     * @param string $data
     * @throws Exception
     * @return static
     */
    public function update($data)
    {
        $queryObject = $this->getQuery('update', $data);

        $this->fireEvents('before-update', $queryObject);

        list($response, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
        $this->fireEvents('after-update', $queryObject, $executionTime);

        return $response;
    }

    /**
     * @param $data
     * @throws Exception
     * @return array|string
     */
    public function updateOrInsert($data)
    {
        if ($this->first()) {
            return $this->update($data);
        }

        return $this->insert($data);
    }

    /**
     * @param string $data
     * @return static
     */
    public function onDuplicateKeyUpdate($data)
    {
        $this->addStatement('onduplicate', $data);

        return $this;
    }

    /**
     * @throws Exception
     */
    public function delete()
    {
        $queryObject = $this->getQuery('delete');

        $this->fireEvents('before-delete', $queryObject);

        list($response, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
        $this->fireEvents('after-delete', $queryObject, $executionTime);

        return $response;
    }

    /**
     * @param $tables array|string Single table or multiple tables as an array or as multiple parameters
     * @throws Exception
     * @return static
     */
    public function table($tables)
    {
        if (!is_array($tables)) {
            // because a single table is converted to an array anyways,
            // this makes sense.
            $tables = func_get_args();
        }

        $instance = new static($this->connection);
        $tables = $this->addTablePrefix($tables, false);
        $instance->addStatement('tables', $tables);

        return $instance;
    }

    /**
     * @param array|string $tables
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
     * @param array|string $fields
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
     * @param array|string $fields
     * @return static
     */
    public function selectDistinct($fields)
    {
        $this->select($fields);
        $this->addStatement('distinct', true);

        return $this;
    }

    /**
     * @param string|array $field
     *
     * @return static
     */
    public function groupBy($field)
    {
        $field = $this->addTablePrefix($field);
        $this->addStatement('groupBys', $field);

        return $this;
    }

    /**
     * @param string|array $fields
     * @param string $defaultDirection
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
            $type = $value;
            if (is_int($key)) {
                $field = $value;
                $type = $defaultDirection;
            }
            if (!$field instanceof Raw) {
                $field = $this->addTablePrefix($field);
            }
            $this->statements['orderBys'][] = compact('field', 'type');
        }

        return $this;
    }

    /**
     * @param int $limit
     * @return static
     */
    public function limit($limit)
    {
        $this->statements['limit'] = $limit;

        return $this;
    }

    /**
     * @param int $offset
     * @return static $this
     */
    public function offset($offset)
    {
        $this->statements['offset'] = $offset;

        return $this;
    }

    /**
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     * @param string $joiner
     *
     * @return static
     */
    public function having($key, $operator, $value, $joiner = 'AND')
    {
        $key = $this->addTablePrefix($key);
        $this->statements['havings'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     *
     * @return static
     */
    public function orHaving($key, $operator, $value)
    {
        return $this->having($key, $operator, $value, 'OR');
    }

    /**
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     *
     * @return static
     */
    public function where($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if (is_bool($value)) {
            $value = (int)$value;
        }

        return $this->whereHandler($key, $operator, $value);
    }

    /**
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     *
     * @return static
     */
    public function orWhere($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR');
    }

    /**
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     *
     * @return static
     */
    public function whereNot($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'AND NOT');
    }

    /**
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     *
     * @return static
     */
    public function orWhereNot($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR NOT');
    }

    /**
     * @param string $key
     * @param array $values
     *
     * @return static
     */
    public function whereIn($key, $values)
    {
        return $this->whereHandler($key, 'IN', $values, 'AND');
    }

    /**
     * @param string $key
     * @param array $values
     *
     * @return static
     */
    public function whereNotIn($key, $values)
    {
        return $this->whereHandler($key, 'NOT IN', $values, 'AND');
    }

    /**
     * @param string $key
     * @param array $values
     *
     * @return static
     */
    public function orWhereIn($key, $values)
    {
        return $this->whereHandler($key, 'IN', $values, 'OR');
    }

    /**
     * @param string $key
     * @param array $values
     *
     * @return static
     */
    public function orWhereNotIn($key, $values)
    {
        return $this->whereHandler($key, 'NOT IN', $values, 'OR');
    }

    /**
     * @param string $key
     * @param string $valueFrom
     * @param string $valueTo
     *
     * @return static
     */
    public function whereBetween($key, $valueFrom, $valueTo)
    {
        return $this->whereHandler($key, 'BETWEEN', [$valueFrom, $valueTo], 'AND');
    }

    /**
     * @param string $key
     * @param string $valueFrom
     * @param string $valueTo
     *
     * @return static
     */
    public function orWhereBetween($key, $valueFrom, $valueTo)
    {
        return $this->whereHandler($key, 'BETWEEN', [$valueFrom, $valueTo], 'OR');
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function whereNull($key)
    {
        return $this->whereNullHandler($key);
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function whereNotNull($key)
    {
        return $this->whereNullHandler($key, 'NOT');
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function orWhereNull($key)
    {
        return $this->whereNullHandler($key, '', 'or');
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function orWhereNotNull($key)
    {
        return $this->whereNullHandler($key, 'NOT', 'or');
    }

    protected function whereNullHandler($key, $prefix = '', $operator = '')
    {
        $key = $this->adapterInstance->wrapSanitizer($this->addTablePrefix($key));

        return $this->{$operator . 'Where'}($this->raw("{$key} IS {$prefix} NULL"));
    }

    /**
     * @param string $table
     * @param string $key
     * @param string|null $operator
     * @param string|null $value
     * @param string $type
     *
     * @return static
     */
    public function join($table, $key, $operator = null, $value = null, $type = 'inner')
    {
        if (($key instanceof \Closure) === false) {
            $key = function (JoinBuilder $joinBuilder) use ($key, $operator, $value) {
                $joinBuilder->on($key, $operator, $value);
            };
        }

        // Build a new JoinBuilder class, keep it by reference so any changes made
        // in the closure should reflect here
        $joinBuilder = $this->container->build(JoinBuilder::class, [$this->connection]);
        //$joinBuilder = &$joinBuilder;

        // Call the closure with our new joinBuilder object
        $key($joinBuilder);
        $table = $this->addTablePrefix($table, false);
        // Get the criteria only query from the joinBuilder object
        $this->statements['joins'][] = compact('type', 'table', 'joinBuilder');

        return $this;
    }

    /**
     * Runs a transaction
     *
     * @param \Closure $callback
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

            // If no errors have been thrown or the transaction wasn't completed within
            // the closure, commit the changes
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
     * @param string $table
     * @param string $key
     * @param string|null $operator
     * @param string|null $value
     *
     * @return static
     */
    public function leftJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'left');
    }

    /**
     * @param string $table
     * @param string $key
     * @param string|null $operator
     * @param string|null $value
     *
     * @return static
     */
    public function rightJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'right');
    }

    /**
     * @param string $table
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     *
     * @return static
     */
    public function innerJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'inner');
    }

    /**
     * Add a raw query
     *
     * @param string $value
     * @param array $bindings
     *
     * @return Raw
     */
    public function raw($value, $bindings = [])
    {
        return $this->container->build(Raw::class, [$value, $bindings]);
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
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param string $key
     * @param string|mixed $operator
     * @param string|mixed $value
     * @param string $joiner
     *
     * @return static
     */
    protected function whereHandler($key, $operator = null, $value = null, $joiner = 'AND')
    {
        $key = $this->addTablePrefix($key);
        $this->statements['wheres'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * Add table prefix (if given) on given string.
     *
     * @param $values
     * @param bool $tableFieldMix If we have mixes of field and table names with a "."
     *
     * @return array|string
     */
    public function addTablePrefix($values, $tableFieldMix = true)
    {
        if ($this->tablePrefix === null) {
            return $values;
        }

        // $value will be an array and we will add prefix to all table names

        // If supplied value is not an array then make it one
        $single = false;
        if (!is_array($values)) {
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
     * @param string $key
     * @param mixed $value
     */
    protected function addStatement($key, $value)
    {
        if (array_key_exists($key, $this->statements) === false) {
            $this->statements[$key] = (array)$value;
        } else {
            $this->statements[$key] = array_merge($this->statements[$key], (array)$value);
        }
    }

    /**
     * @param string $event
     * @param string|null $table
     *
     * @return \Closure|null
     */
    public function getEvent($event, $table = null)
    {
        return $this->connection->getEventHandler()->getEvent($event, $table);
    }

    /**
     * @param string $event
     * @param string|null $table
     * @param \Closure $action
     *
     * @return void
     */
    public function registerEvent($event, $table = null, \Closure $action)
    {
        $this->connection->getEventHandler()->registerEvent($event, $table, $action);
    }

    /**
     * @param string $event
     * @param string|null $table
     *
     * @return void
     */
    public function removeEvent($event, $table = null)
    {
        $this->connection->getEventHandler()->removeEvent($event, $table);
    }

    /**
     * @param      $event
     * @return mixed
     */
    public function fireEvents($event)
    {
        $params = func_get_args();
        array_unshift($params, $this);

        return call_user_func_array([$this->connection->getEventHandler(), 'fireEvents'], $params);
    }

    /**
     * @return array
     */
    public function getStatements()
    {
        return $this->statements;
    }
}