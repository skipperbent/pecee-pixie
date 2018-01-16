<?php

namespace Pecee\Pixie;

use Pecee\Pixie\QueryBuilder\QueryObject;
use Throwable;

/**
 * Class Exception
 *
 * @package Pecee\Pixie
 */
class Exception extends \Exception
{

    protected $query;

    public function __construct($message = "", $code = 0, Throwable $previous = null, QueryObject $query = null)
    {
        parent::__construct($message, $code, $previous);
        $this->query = $query;
    }

    /**
     * Get query-object
     *
     * @return QueryObject
     */
    public function getQuery()
    {
        return $this->query;
    }

}
