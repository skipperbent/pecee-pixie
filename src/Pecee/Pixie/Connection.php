<?php

namespace Pecee\Pixie;

use Pecee\Pixie\ConnectionAdapters\IConnectionAdapter;
use Pecee\Pixie\Event\EventHandler;
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
     * @var Connection
     */
    protected static $storedConnection;

    /**
     * Connection adapter (i.e. Mysql, Pgsql, Sqlite)
     *
     * @var IConnectionAdapter
     */
    protected static $adapter;

    /**
     * @var array
     */
    protected static $adapterConfig;

    /**
     * @var \PDO
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
     * @param string $adapter Adapter name or class
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
            ->setAdapterConfig($adapterConfig)
            ->connect();

        // Create event dependency
        $this->eventHandler = new EventHandler();
    }

    /**
     * @return Connection
     */
    public static function getStoredConnection(): self
    {
        if (static::$storedConnection === null && static::$adapter !== null && static::$adapterConfig !== null) {
            static::$storedConnection = new static(static::$adapter, static::$adapterConfig);
        }

        return static::$storedConnection;
    }

    /**
     * Create the connection adapter
     */
    public function connect()
    {
        // Build a database connection if we don't have one connected

        $pdo = $this->getAdapter()->connect($this->getAdapterConfig());
        $this->setPdoInstance($pdo);

        // Preserve the first database connection with a static property
        if (static::$storedConnection === null) {
            static::$storedConnection = $this;
        }
    }

    /**
     * @return IConnectionAdapter
     */
    public function getAdapter(): IConnectionAdapter
    {
        return static::$adapter;
    }

    /**
     * @return array
     */
    public function getAdapterConfig(): array
    {
        return static::$adapterConfig;
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
     * @throws \Pecee\Pixie\Exception
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
        static::$adapter = $adapter;

        return $this;
    }

    /**
     * @param array $adapterConfig
     *
     * @return static
     */
    public function setAdapterConfig(array $adapterConfig): self
    {
        static::$adapterConfig = $adapterConfig;

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
    public function setLastQuery(QueryObject $query) : self
    {
        $this->lastQuery = $query;

        return $this;
    }

    /**
     * Get query-object from last executed query.
     *
     * @return QueryObject|null
     */
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    /**
     * Register new event
     *
     * @param string $name
     * @param string|null $table
     * @param \Closure $action
     *
     * @return void
     */
    public function registerEvent($name, $table = null, \Closure $action)
    {
        $this->getEventHandler()->registerEvent($name, $table, $action);
    }

    /**
     * Close PDO connection
     */
    public function close()
    {
        $this->pdoInstance = null;
        static::$storedConnection = null;
    }

    public function __destruct()
    {
        $this->close();
    }

}