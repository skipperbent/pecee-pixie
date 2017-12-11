<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;

/**
 * Class Mysql
 *
 * @package Pecee\Pixie\ConnectionAdapters
 */
class Mysql extends BaseAdapter {
	/**
	 * @param array $config
	 *
	 * @return PDO
	 * @throws Exception
	 */
	protected function doConnect(array $config): PDO {
		if (\extension_loaded('pdo_mysql') === false) {
			throw new Exception(sprintf('%s library not loaded', 'pdo_mysql'));
		}

		$connectionString = "mysql:dbname={$config['database']}";

		if (isset($config['host']) === true) {
			$connectionString .= ";host={$config['host']}";
		}

		if (isset($config['port']) === true) {
			$connectionString .= ";port={$config['port']}";
		}

		if (isset($config['unix_socket']) === true) {
			$connectionString .= ";unix_socket={$config['unix_socket']}";
		}

		$connection = new PDO($connectionString, $config['username'], $config['password'], $config['options']);

		if (isset($config['charset'])) {
			$connection->prepare("SET NAMES '{$config['charset']}'")->execute();
		}

		return $connection;
	}

	/**
	 * Get query adapter class
	 * @return string
	 */
	public function getQueryAdapterClass(): string {
		return \Pecee\Pixie\QueryBuilder\Adapters\Mysql::class;
	}
}
