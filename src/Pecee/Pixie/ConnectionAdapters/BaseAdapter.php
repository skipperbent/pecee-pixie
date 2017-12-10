<?php

namespace Pecee\Pixie\ConnectionAdapters;

/**
 * Class BaseAdapter
 *
 * @package Pecee\Pixie\ConnectionAdapters
 */
abstract class BaseAdapter implements IConnectionAdapter
{

    /**
     * @param $config
     * @return \PDO
     */
    public function connect($config)
    {
        if (isset($config['options']) === false) {
            $config['options'] = [];
        }

        return $this->doConnect($config);
    }

    /**
     * @param array $config
     * @return mixed
     */
    abstract protected function doConnect(array $config);
}
