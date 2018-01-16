<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;
use Pecee\Pixie\Exception;

/**
 * Class Pgsql
 *
 * @package Pecee\Pixie\ConnectionAdapters
 */
class Pgsql extends BaseAdapter
{
    /**
     * @param array $config
     *
     * @return PDO
     * @throws Exception
     */
    protected function doConnect(array $config)
    {
        if (extension_loaded('pdo_pgsql') === false) {
            throw new Exception(sprintf('%s library not loaded', 'pdo_pgsql'));
        }

        $connectionString = "pgsql:host={$config['host']};dbname={$config['database']}";

        if (isset($config['port']) === true) {
            $connectionString .= ";port={$config['port']}";
        }

        try {

            /**
             * @var \PDO $connection
             */
            $connection = new PDO($connectionString, $config['username'], $config['password'], $config['options']);

            if (isset($config['charset']) === true) {
                $connection->prepare("SET NAMES '{$config['charset']}'")->execute();
            }

            if (isset($config['schema']) === true) {
                $connection->prepare("SET search_path TO '{$config['schema']}'")->execute();
            }

        } catch (\PDOException $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $connection;
    }

    public function getQueryAdapterClass()
    {
        return \Pecee\Pixie\QueryBuilder\Adapters\Pgsql::class;
    }

}
