<?php

namespace Pecee\Pixie;

use Pecee\Pixie\ConnectionAdapters\IConnectionAdapter;
use Pecee\Pixie\Event\EventHandler;
use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;
use Pecee\Pixie\QueryBuilder\QueryObject;

/**
 * Class Connection
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
     * @param string $adapter       Adapter name or class
     * @param array  $adapterConfig
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

        if (null === static::$storedConnection) {
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
     *
     * @return static
     */
    public function connect(): self
    {
        if (null !== $this->pdoInstance) {
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
     * @throws Exception
     *
     * @return QueryBuilderHandler
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
     *
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
     * @param string      $name
     * @param string|null $table
     * @param \Closure    $action
     *
     * @return void
     */
    public function registerEvent($name, $table = null, \Closure $action): void
    {
        $this->getEventHandler()->registerEvent($name, $table, $action);
    }

    /**
     * Close PDO connection
     */
    public function close(): void
    {
        $this->pdoInstance        = null;
        static::$storedConnection = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
