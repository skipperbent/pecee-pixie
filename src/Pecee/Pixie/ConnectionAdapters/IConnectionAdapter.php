<?php

namespace Pecee\Pixie\ConnectionAdapters;

interface IConnectionAdapter {

	/**
	 * Connect to database
	 *
	 * @param array $config
	 *
	 * @return \PDO
	 */
	public function connect($config);

	/**
	 * Get query adapter class
	 * @return string
	 */
	public function getQueryAdapterClass();

}