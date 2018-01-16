<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;
use Pecee\Pixie\Exception;

/**
 * Class Sqlite
 *
 * @package Pecee\Pixie\ConnectionAdapters
 */
class Sqlite extends BaseAdapter
{
    /**
     * @param array $config
     *
     * @return PDO
     * @throws \Pecee\Pixie\Exception
     */
    public function doConnect(array $config)
    {
        if (extension_loaded('pdo_sqlite') === false) {
            throw new Exception(sprintf('%s library not loaded', 'pdo_sqlite'));
        }

        $connectionString = 'sqlite:' . $config['database'];

        try {
            return new PDO($connectionString, null, null, $config['options']);
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    public function getQueryAdapterClass()
    {
        return \Pecee\Pixie\QueryBuilder\Adapters\Sqlite::class;
    }

}
