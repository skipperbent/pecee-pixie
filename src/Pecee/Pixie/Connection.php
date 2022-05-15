<?php

namespace Pecee\Pixie;

use PDOException;
use Pecee\Pixie\ConnectionAdapters\IConnectionAdapter;
use Pecee\Pixie\Event\EventHandler;
use Pecee\Pixie\Exceptions\TransactionException;
use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;
use Pecee\Pixie\QueryBuilder\QueryObject;

/**
 * Class Connection
 *
 * @package Pecee\Pixie
 */
class Connection
{

    /**
     * @var Connection|null
     */
    protected static $storedConnection;

    /**
     * Connection adapter (i.e. Mysql, Pgsql, Sqlite)
     *
     * @var IConnectionAdapter
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $adapterConfig;

    /**
     * @var \PDO|null
     */
    protected $pdoInstance;

    /**
     * @var EventHandler
     */
    protected $eventHandler;

    /**
     * @var QueryObject|null
     */
    protected $lastQuery;

    /**
     * @param string|IConnectionAdapter $adapter Adapter name or class
     * @param array $adapterConfig
     */
    public function __construct($adapter, array $adapterConfig)
    {
        if (($adapter instanceof IConnectionAdapter) === false) {
            /* @var $adapter IConnectionAdapter */
            $adapter = '\Pecee\Pixie\ConnectionAdapters\\' . ucfirst(strtolower($adapter));
            $adapter = new $adapter();
        }

        $this
            ->setAdapter($adapter)
            ->setAdapterConfig($adapterConfig);

        // Create event dependency
        $this->eventHandler = new EventHandler();

        if (static::$storedConnection === null) {
            static::$storedConnection = $this;
        }
    }

    /**
     * @return Connection
     */
    public static function getStoredConnection(): ?self
    {
        return static::$storedConnection;
    }

    /**
     * Create the connection adapter and connect to database
     * @return static
     */
    public function connect(): self
    {
        if ($this->pdoInstance !== null) {
            return $this;
        }

        // Build a database connection if we don't have one connected
        $this->setPdoInstance($this->getAdapter()->connect($this->getAdapterConfig()));

        return $this;
    }

    /**
     * @return IConnectionAdapter
     */
    public function getAdapter(): IConnectionAdapter
    {
        return $this->adapter;
    }

    /**
     * @return array
     */
    public function getAdapterConfig(): array
    {
        return $this->adapterConfig;
    }

    /**
     * @return EventHandler
     */
    public function getEventHandler(): EventHandler
    {
        return $this->eventHandler;
    }

    /**
     * @return \PDO
     */
    public function getPdoInstance(): \PDO
    {
        return $this->pdoInstance;
    }

    /**
     * Returns an instance of Query Builder
     *
     * @return QueryBuilderHandler
     * @throws Exception
     */
    public function getQueryBuilder(): QueryBuilderHandler
    {
        return new QueryBuilderHandler($this);
    }

    /**
     * @param IConnectionAdapter $adapter
     *
     * @return static
     */
    public function setAdapter(IConnectionAdapter $adapter): self
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @param array $adapterConfig
     *
     * @return static
     */
    public function setAdapterConfig(array $adapterConfig): self
    {
        $this->adapterConfig = $adapterConfig;

        return $this;
    }

    /**
     * @param \PDO $pdo
     *
     * @return static
     */
    public function setPdoInstance(\PDO $pdo): self
    {
        $this->pdoInstance = $pdo;

        return $this;
    }

    /**
     * Set query-object for last executed query.
     *
     * @param QueryObject $query
     * @return static
     */
    public function setLastQuery(QueryObject $query): self
    {
        $this->lastQuery = $query;

        return $this;
    }

    /**
     * Get query-object from last executed query.
     *
     * @return QueryObject|null
     */
    public function getLastQuery(): ?QueryObject
    {
        return $this->lastQuery;
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
        $this->getEventHandler()->registerEvent($name, $action, $table);
    }

    /**
     * Close PDO connection
     */
    public function close(): void
    {
        $this->pdoInstance = null;
        static::$storedConnection = null;
    }

    /**
     * Initiates a transaction
     * 
     * Turns off autocommit mode. While autocommit mode is turned off, changes made 
     * to the database via the PDO object instance are not committed 
     * until you end the transaction by calling commit().
     * 
     * Calling rollBack() will roll back all changes to the database and return the 
     * connection to autocommit mode.Some databases automatically issue an implicit 
     * COMMIT when a database definition language (DDL) statement such as DROP TABLE
     * or CREATE TABLE is issued within a transaction. The implicit COMMIT will 
     * prevent you from rolling back any other changes within the transaction boundary
     * 
     * @throws TransactionException If there is already a transaction started or the driver 
     * does not support transactions. Note: An exception is raised even when the 
     * PDO::ATTR_ERRMODE attribute is not PDO::ERRMODE_EXCEPTION.
     * 
     * @return bool — TRUE on success or FALSE on failure.
     */
    public function beginTransaction(): bool
    {
        try {
            return $this->getPdoInstance()->beginTransaction();
        } catch (PDOException $ex) {
            throw new TransactionException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * Checks if inside a transaction
     * 
     * @return bool — TRUE if a transaction is currently active, and FALSE if not.
     */
    public function inTransaction(): bool
    {
        return $this->getPdoInstance()->inTransaction();
    }

    /**
     * Commits a transaction, returning the database connection to autocommit mode 
     * until the next call to beginTransaction() starts a new transaction.
     * 
     * Calls PDO::commit()
     * 
     * @return bool — TRUE on success or FALSE on failure.
     * @throws TransactionException — if there is no active transaction.
     * @link https://php.net/manual/en/pdo.commit.php
     */
    public function commit(): bool
    {
        try {
            return $this->getPdoInstance()->commit();
        } catch (PDOException $ex) {
            throw new TransactionException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * Rolls back a transaction
     * 
     * Calls PDO::rollBack()
     * 
     * @return bool — TRUE on success or FALSE on failure.
     * @throws TransactionException — if there is no active transaction.
     * @link https://php.net/manual/en/pdo.rollback.php
     */
    public function rollBack(): bool
    {
        try {
            return $this->getPdoInstance()->rollBack();
        } catch (PDOException $ex) {
            throw new TransactionException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
