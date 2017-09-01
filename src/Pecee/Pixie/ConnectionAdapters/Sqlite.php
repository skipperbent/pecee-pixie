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
     * @return mixed
     */
    public function doConnect($config)
    {
        $connectionString = 'sqlite:' . $config['database'];

        return $this->container->build(
            \PDO::class,
            [$connectionString, null, null, $config['options']]
        );
    }
}
