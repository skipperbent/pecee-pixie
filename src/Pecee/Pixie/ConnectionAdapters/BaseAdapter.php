<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;
use Viocon\Container;

/**
 * Class BaseAdapter
 *
 * @package Pecee\Pixie\ConnectionAdapters
 */
abstract class BaseAdapter
{
    /**
     * @var \Viocon\Container
     */
    protected $container;

    /**
     * @param \Viocon\Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param $config
     * @return \PDO
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
     * @return mixed
     */
    abstract protected function doConnect(array $config);
}
