<?php

namespace Pecee\Pixie\Event;

use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;
use Pecee\Pixie\QueryBuilder\QueryObject;

class EventArguments
{
    /**
     * Event name
     * @var string
     */
    private $name;

    /**
     * @var QueryObject
     */
    private $queryObject;

    /**
     * @var QueryBuilderHandler
     */
    private $queryBuilder;

    /**
     * @var array
     */
    private $arguments;

    /**
     * EventArguments constructor.
     *
     * @param string                                        $name
     * @param \Pecee\Pixie\QueryBuilder\QueryObject         $qo
     * @param \Pecee\Pixie\QueryBuilder\QueryBuilderHandler $qb
     * @param array                                         $arguments
     */
    public function __construct(string $name, QueryObject $qo, QueryBuilderHandler $qb, array $arguments)
    {
        $this->name = $name;
        $this->queryObject = $qo;
        $this->queryBuilder = $qb;
        $this->arguments = $arguments;
    }

    /**
     * Get event name
     *
     * @return string
     */
    public function getEventName(): string
    {
        return $this->name;
    }

    /**
     * Get QueryBuilder object
     *
     * @return QueryBuilderHandler
     */
    public function getQueryBuilder(): QueryBuilderHandler
    {
        return $this->queryBuilder;
    }

    /**
     * Get query object
     *
     * @return QueryObject
     */
    public function getQuery(): QueryObject
    {
        return $this->queryObject;
    }

    /**
     * Get insert id from last query
     *
     * @return string|null
     */
    public function getInsertId() : ?string
    {
        return $this->arguments['insert_id'] ?? null;
    }

    /**
     * Get execution time
     *
     * @return float|null
     */
    public function getExecutionTime() : ?float
    {
        return $this->arguments['execution_time'] ?? null;
    }

    /**
     * Get arguments
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

}
