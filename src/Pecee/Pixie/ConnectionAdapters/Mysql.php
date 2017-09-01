<?php

namespace Pecee\Pixie\ConnectionAdapters;

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
     * @return mixed
     * @throws Exception
     */
    protected function doConnect($config)
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new Exception(sprintf('%s library not loaded', 'pdo_mysql'));
        }

        $connectionString = "mysql:dbname={$config['database']}";

        if (isset($config['host'])) {
            $connectionString .= ";host={$config['host']}";
        }

        if (isset($config['port'])) {
            $connectionString .= ";port={$config['port']}";
        }

        if (isset($config['unix_socket'])) {
            $connectionString .= ";unix_socket={$config['unix_socket']}";
        }

        $connection = $this->container->build(
            \PDO::class,
            [$connectionString, $config['username'], $config['password'], $config['options']]
        );

        if (isset($config['charset'])) {
            $connection->prepare("SET NAMES '{$config['charset']}'")->execute();
        }

        return $connection;
    }
}
