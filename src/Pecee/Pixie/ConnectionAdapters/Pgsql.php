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
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     */
    protected function doConnect(array $config): PDO
    {
        if (\extension_loaded('pdo_pgsql') === false) {
            throw new Exception(sprintf('%s library not loaded', 'pdo_pgsql'));
        }

        $connectionString = "pgsql:host={$config['host']};dbname={$config['database']}";

        if (isset($config['port']) === true) {
            $connectionString .= ";port={$config['port']}";
        }

        try {

            $connection = new PDO($connectionString, $config['username'], $config['password'], $config['options']);

            if (isset($config['charset']) === true) {
                $connection->prepare("SET NAMES '{$config['charset']}'")->execute();
            }

            if (isset($config['schema']) === true) {
                $connection->prepare("SET search_path TO '{$config['schema']}'")->execute();
            }

        } catch (\PDOException $e) {
            throw Exception::create($e, $this->getQueryAdapterClass());
        }

        return $connection;
    }

    /**
     * Get query adapter class
     * @return string
     */
    public function getQueryAdapterClass(): string
    {
        return \Pecee\Pixie\QueryBuilder\Adapters\Pgsql::class;
    }
}