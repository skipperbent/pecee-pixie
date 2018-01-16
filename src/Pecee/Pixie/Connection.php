<?php

namespace Pecee\Pixie;

use Pecee\Pixie\ConnectionAdapters\IConnectionAdapter;
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
    protected $adapter;

    /**
     * @var array
     */
    protected $adapterConfig;

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
    public static function getStoredConnection(): Connection
    {
        return static::$storedConnection;
    }

    /**
     * Create the connection adapter
     */
    public function connect()
    {
        // Build a database connection if we don't have one connected

        $pdo = $this->adapter->connect($this->adapterConfig);
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
    public function setAdapter(IConnectionAdapter $adapter): Connection
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @param array $adapterConfig
     *
     * @return static
     */
    public function setAdapterConfig(array $adapterConfig): Connection
    {
        $this->adapterConfig = $adapterConfig;

        return $this;
    }

    /**
     * @param \PDO $pdo
     *
     * @return static
     */
    public function setPdoInstance(\PDO $pdo): Connection
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
    public function setLastQuery(QueryObject $query)
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

}
