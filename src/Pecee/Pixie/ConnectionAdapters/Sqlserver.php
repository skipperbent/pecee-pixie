<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;
use Pecee\Pixie\Exception;

/**
 * Class Sqlserver
 */
class Sqlserver extends BaseAdapter
{
    /**
     * @param array $config
     *
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     *
     * @return PDO
     */
    protected function doConnect(array $config): PDO
    {
        if (false === \extension_loaded('pdo_sqlsrv')) {
            throw new Exception(sprintf('%s library not loaded', 'pdo_sqlsrv'));
        }
        $connectionString = "sqlsrv:database={$config['database']}";
        if (true === isset($config['host'])) {
            $connectionString .= ";server={$config['host']}";
        }
        if (true === isset($config['port'])) {
            $connectionString .= ";port={$config['port']}";
        }
        try {
            $connection = new PDO($connectionString, $config['username'], $config['password'], $config['options']);
        } catch (\PDOException $e) {
            throw Exception::create($e, $this->getQueryAdapterClass());
        }

        return $connection;
    }

    /**
     * Get query adapter class
     *
     * @return string
     */
    public function getQueryAdapterClass(): string
    {
        return \Pecee\Pixie\QueryBuilder\Adapters\Sqlserver::class;
    }
}
