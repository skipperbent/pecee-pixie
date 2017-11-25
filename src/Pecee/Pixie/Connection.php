<?php

namespace Pecee\Pixie;

use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;
use Viocon\Container;

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
     * @var Container
     */
    protected $container;
    /**
     * Name of DB adapter (i.e. Mysql, Pgsql, Sqlite)
     *
     * @var string
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
     * @param string         $adapter
     * @param array          $adapterConfig
     * @param Container|null $container
     */
    public function __construct(string $adapter, array $adapterConfig, Container $container = null)
    {
        $this->container = $container ?? new Container();

        $this
            ->setAdapter($adapter)
            ->setAdapterConfig($adapterConfig)
            ->connect()
        ;

        // Create event dependency
        $this->eventHandler = $this->container->build(EventHandler::class);
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
    protected function connect()
    {
        // Build a database connection if we don't have one connected

        $adapter = '\Pecee\Pixie\ConnectionAdapters\\' . ucfirst(strtolower($this->adapter));

        $adapterInstance = $this->container->build($adapter, [$this->container]);

        $pdo = $adapterInstance->connect($this->adapterConfig);
        $this->setPdoInstance($pdo);

        // Preserve the first database connection with a static property
        if (static::$storedConnection === null) {
            static::$storedConnection = $this;
        }
    }

    /**
     * @return string
     */
    public function getAdapter(): string
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
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
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
     */
    public function getQueryBuilder(): QueryBuilderHandler
    {
        return $this->container->build(QueryBuilderHandler::class, [$this]);
    }

    /**
     * @param string $adapter
     *
     * @return \Pecee\Pixie\Connection
     */
    public function setAdapter(string $adapter): Connection
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @param array $adapterConfig
     *
     * @return \Pecee\Pixie\Connection
     */
    public function setAdapterConfig(array $adapterConfig): Connection
    {
        $this->adapterConfig = $adapterConfig;

        return $this;
    }

    /**
     * @param \PDO $pdo
     *
     * @return \Pecee\Pixie\Connection
     */
    public function setPdoInstance(\PDO $pdo): Connection
    {
        $this->pdoInstance = $pdo;

        return $this;
    }
}
