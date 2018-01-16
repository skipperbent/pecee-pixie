<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;
use Pecee\Pixie\Exception;

/**
 * Class Mysql
 *
 * @package Pecee\Pixie\ConnectionAdapters
 */
class Mysql extends BaseAdapter
{
    /**
     * @param array $config
     *
     * @return PDO
     * @throws Exception
     */
    protected function doConnect(array $config)
    {
        if (extension_loaded('pdo_mysql') === false) {
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

        try {

            $connection = new PDO($connectionString, $config['username'], $config['password'], $config['options']);

            if (isset($config['charset']) === true) {
                $connection->prepare("SET NAMES '{$config['charset']}'")->execute();
            }

        } catch (\PDOException $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $connection;
    }

    public function getQueryAdapterClass()
    {
        return \Pecee\Pixie\QueryBuilder\Adapters\Mysql::class;
    }
}
