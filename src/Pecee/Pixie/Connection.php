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
     * Create the connection adapter
     */
    public function connect()
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
     * @return Connection
     */
    public static function getStoredConnection(): Connection
    {
        return static::$storedConnection;
    }

    /**
     * @return string
     */
    public function getAdapter(): string
    {
        return $this->adapter;
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
     * @return array
     */
    public function getAdapterConfig(): array
    {
        return $this->adapterConfig;
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
     * @param \PDO $pdo
     *
     * @return \Pecee\Pixie\Connection
     */
    public function setPdoInstance(\PDO $pdo): Connection
    {
        $this->pdoInstance = $pdo;

        return $this;
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
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdoInstance->inTransaction();
    }

    /**
     * @param bool $inTransaction
     *
     * @return $this
     */
    public function transactionBegin(bool $inTransaction = false)
    {
        if (false === $inTransaction) {
            var_dump(['begin success',$this->inTransaction()]);
            $this->pdoInstance->beginTransaction();
        }else{
            var_dump(['begin omit',$this->inTransaction()]);
        }

        return $this;
    }

    /**
     * @param bool $inTransaction
     *
     * @return $this
     */
    public function transactionCommit(bool $inTransaction = false)
    {
        if (false === $inTransaction) {
            var_dump(['commit success',$this->inTransaction()]);
            $this->pdoInstance->commit();
        }else{
            var_dump(['commit omit',$this->inTransaction()]);
        }

        return $this;
    }

    /**
     * @param bool $inTransaction
     *
     * @return $this
     */
    public function transactionRollBack(bool $inTransaction = false)
    {
        if (false === $inTransaction) {
            var_dump(['rollback success',$this->inTransaction()]);
            $this->pdoInstance->rollBack();
        }else{
            var_dump(['rollback omit',$this->inTransaction()]);
        }

        return $this;
    }
}
