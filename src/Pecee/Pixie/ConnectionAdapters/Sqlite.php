<?php

namespace Pecee\Pixie\ConnectionAdapters;

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
     * @return \PDO
     * @throws Exception
     */
    public function doConnect(array $config)
    {
        if (\extension_loaded('pdo_sqlite') === false) {
            throw new Exception(sprintf('%s library not loaded', 'pdo_sqlite'));
        }

        $connectionString = 'sqlite:' . $config['database'];

        return $this->container->build(
            \PDO::class,
            [$connectionString, null, null, $config['options']]
        );
    }
}
