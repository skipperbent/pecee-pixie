<?php

namespace Pecee\Pixie;

use Pecee\Pixie\ConnectionAdapters\IConnectionAdapter;
use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;

/**
 * Class Connection
 *
 * @package Pecee\Pixie
 */
class Connection {

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
	 * @param string $adapter Adapter name or class
	 * @param array $adapterConfig
	 */
	public function __construct($adapter, array $adapterConfig) {
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
	public static function getStoredConnection(): Connection {
		return static::$storedConnection;
	}

	/**
	 * Create the connection adapter
	 */
	public function connect() {
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
	public function getAdapter(): IConnectionAdapter {
		return $this->adapter;
	}

	/**
	 * @return array
	 */
	public function getAdapterConfig(): array {
		return $this->adapterConfig;
	}

	/**
	 * @return EventHandler
	 */
	public function getEventHandler(): EventHandler {
		return $this->eventHandler;
	}

	/**
	 * @return \PDO
	 */
	public function getPdoInstance(): \PDO {
		return $this->pdoInstance;
	}

	/**
	 * Returns an instance of Query Builder
	 *
	 * @return QueryBuilderHandler
	 * @throws Exception
	 */
	public function getQueryBuilder(): QueryBuilderHandler {
		return new QueryBuilderHandler($this);
	}

	/**
	 * @param IConnectionAdapter $adapter
	 *
	 * @return \Pecee\Pixie\Connection
	 */
	public function setAdapter(IConnectionAdapter $adapter): Connection {
		$this->adapter = $adapter;

		return $this;
	}

	/**
	 * @param array $adapterConfig
	 *
	 * @return \Pecee\Pixie\Connection
	 */
	public function setAdapterConfig(array $adapterConfig): Connection {
		$this->adapterConfig = $adapterConfig;

		return $this;
	}

	/**
	 * @param \PDO $pdo
	 *
	 * @return \Pecee\Pixie\Connection
	 */
	public function setPdoInstance(\PDO $pdo): Connection {
		$this->pdoInstance = $pdo;

		return $this;
	}
}
