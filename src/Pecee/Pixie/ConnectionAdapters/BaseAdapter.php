<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;

/**
 * Class BaseAdapter
 */
abstract class BaseAdapter implements IConnectionAdapter
{
    /**
     * @param array $config
     *
     * @return PDO
     */
    public function connect(array $config): PDO
    {
        if (false === isset($config['options'])) {
            $config['options'] = [];
        }

        return $this->doConnect($config);
    }

    /**
     * @param array $config
     *
     * @return PDO
     */
    abstract protected function doConnect(array $config): PDO;
}
