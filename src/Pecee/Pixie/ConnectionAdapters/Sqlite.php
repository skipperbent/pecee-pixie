<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;

/**
 * Class Sqlite
 *
 * @package Pecee\Pixie\ConnectionAdapters
 */
class Sqlite extends BaseAdapter {
	/**
	 * @param array $config
	 *
	 * @return PDO
	 * @throws Exception
	 */
	public function doConnect(array $config): PDO {
		if (\extension_loaded('pdo_sqlite') === false) {
			throw new Exception(sprintf('%s library not loaded', 'pdo_sqlite'));
		}

		$connectionString = 'sqlite:' . $config['database'];

		return new PDO($connectionString, null, null, $config['options']);
	}

	/**
	 * Get query adapter class
	 * @return string
	 */
	public function getQueryAdapterClass(): string {
		return \Pecee\Pixie\QueryBuilder\Adapters\Sqlite::class;
	}
}
