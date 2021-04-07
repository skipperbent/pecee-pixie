<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;
use Pecee\Pixie\Exception;

/**
 * Class Sqlite
 */
class Sqlite extends BaseAdapter
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
    public function doConnect(array $config): PDO
    {
        if (false === \extension_loaded('pdo_sqlite')) {
            throw new Exception(sprintf('%s library not loaded', 'pdo_sqlite'));
        }

        $connectionString = 'sqlite:' . $config['database'];

        try {
            return new PDO($connectionString, null, null, $config['options']);
        } catch (\PDOException $e) {
            throw Exception::create($e, $this->getQueryAdapterClass());
        }
    }

    /**
     * Get query adapter class
     *
     * @return string
     */
    public function getQueryAdapterClass(): string
    {
        return \Pecee\Pixie\QueryBuilder\Adapters\Sqlite::class;
    }
}
