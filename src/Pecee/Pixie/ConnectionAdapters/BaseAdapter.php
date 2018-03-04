<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;

/**
 * Class BaseAdapter
 *
 * @package Pecee\Pixie\ConnectionAdapters
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
        if (isset($config['options']) === false) {
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