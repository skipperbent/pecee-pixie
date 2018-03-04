<?php

namespace Pecee\Pixie\ConnectionAdapters;

use PDO;

interface IConnectionAdapter
{

    /**
     * Connect to database
     *
     * @param array $config
     *
     * @return PDO
     */
    public function connect(array $config): PDO;

    /**
     * Get query adapter class
     * @return string
     */
    public function getQueryAdapterClass(): string;

}