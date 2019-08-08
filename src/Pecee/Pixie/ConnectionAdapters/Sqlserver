<?php
namespace Pecee\Pixie\ConnectionAdapters;
use PDO;
use Pecee\Pixie\Exception;
/**
 * Class Sqlserver
 *
 * @package Pecee\Pixie\ConnectionAdapters
 */
class Sqlserver extends BaseAdapter
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
        if (\extension_loaded('pdo_sqlsrv') === false) {
            throw new Exception(sprintf('%s library not loaded', 'pdo_sqlsrv'));
        }
        $connectionString = "sqlsrv:database={$config['database']}";
        if (isset($config['host']) === true) {
            $connectionString .= ";server={$config['host']}";
        }
        if (isset($config['port']) === true) {
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
     * @return string
     */
    public function getQueryAdapterClass(): string
    {
        return \Pecee\Pixie\QueryBuilder\Adapters\Sqlserver::class;
    }
}
