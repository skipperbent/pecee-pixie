<?php

namespace Pecee\Pixie\Event;

use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;
use Pecee\Pixie\QueryBuilder\QueryObject;
use Pecee\Pixie\QueryBuilder\Raw;

/**
 * Class EventHandler
 *
 * @package Pecee\Pixie
 */
class EventHandler
{

    /**
     * Event-type that fires before each query
     *
     * @var string
     */
    const EVENT_BEFORE_ALL = 'before-*';

    /**
     * Event-type that fires after each query
     *
     * @var string
     */
    const EVENT_AFTER_ALL = 'after-*';

    /**
     * This event fires before a custom query
     *
     * @var string
     */
    const EVENT_BEFORE_QUERY = 'before-query';

    /**
     * This event fires after a custom query
     *
     * @var string
     */
    const EVENT_AFTER_QUERY = 'after-query';

    /**
     * Event-type that fires before select
     *
     * @var string
     */
    const EVENT_BEFORE_SELECT = 'before-select';

    /**
     * Event-type that fires after select
     *
     * @var string
     */
    const EVENT_AFTER_SELECT = 'after-select';

    /**
     * Event-type that fires before insert
     *
     * @var string
     */
    const EVENT_BEFORE_INSERT = 'before-insert';

    /**
     * Event-type that fires after insert
     *
     * @var string
     */
    const EVENT_AFTER_INSERT = 'after-insert';

    /**
     * Event-type that fires before update
     *
     * @var string
     */
    const EVENT_BEFORE_UPDATE = 'before-update';

    /**
     * Event-type that fires after update
     *
     * @var string
     */
    const EVENT_AFTER_UPDATE = 'after-update';

    /**
     * Event-type that fires before delete
     *
     * @var string
     */
    const EVENT_BEFORE_DELETE = 'before-delete';

    /**
     * Event-type that fires after delete
     *
     * @var string
     */
    const EVENT_AFTER_DELETE = 'after-delete';

    /**
     * Fake table name for any table events
     */
    const TABLE_ANY = ':any';
    /**
     * @var array
     */
    protected $events = [];

    /**
     * @var array
     */
    protected $firedEvents = [];

    /**
     * @param string $event
     * @param QueryObject $queryObject
     * @param QueryBuilderHandler $queryBuilder
     * @param array $eventArguments
     * @return array Event responses array
     */
    public function fireEvents(string $event, QueryObject $queryObject, QueryBuilderHandler $queryBuilder, array $eventArguments = []): array
    {
        $statements = $queryBuilder->getStatements();
        $tables = $statements['tables'] ?? [];

        // Events added with :any will be fired in case of any table,
        // we are adding :any as a fake table at the beginning.
        array_unshift($tables, static::TABLE_ANY);

        $eventResponses = [];

        // Fire all events
        foreach ($tables as $table) {
            // Fire before events for :any table
            $action = $this->getEvent($event, $table);
            if ($action === null) {
                continue;
            }

            // Make an event id, with event type and table
            $eventId = $event . $table;

            // Fire event and add to fired list
            $this->firedEvents[] = $eventId;

            $result = $action(new EventArguments($event, $queryObject, $queryBuilder, $eventArguments));

            if ($result !== null) {
                $eventResponses[] = $result;
            }
        }

        return $eventResponses;
    }

    /**
     * @param string $event
     * @param string|Raw|null $table
     *
     * @return \Closure|null
     */
    public function getEvent(string $event, $table = null)
    {
        $table = $table ?? static::TABLE_ANY;

        if ($table instanceof Raw) {
            return null;
        }

        // Find event with wildcard (*)
        if (isset($this->events[$table]) === true) {
            foreach ((array)$this->events[$table] as $name => $e) {
                if (strpos($name, '*') !== false) {
                    $name = substr($name, 0, strpos($name, '*'));
                    if (strpos($event, $name) !== false) {
                        return $e;
                    }
                }
            }
        }

        return $this->events[$table][$event] ?? null;
    }

    /**
     * @return array
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param string $event
     * @param string $table
     * @param \Closure $action
     *
     * @return void
     */
    public function registerEvent(string $event, string $table = null, \Closure $action)
    {
        $this->events[$table ?? static::TABLE_ANY][$event] = $action;
    }

    /**
     * @param string $event
     * @param string $table
     *
     * @return void
     */
    public function removeEvent($event, $table = null)
    {
        unset($this->events[$table ?? static::TABLE_ANY][$event]);
    }
}