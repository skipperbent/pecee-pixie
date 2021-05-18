<?php

namespace Pecee\Pixie;

/**
 * Class QueryBuilder
 *
 * @package Pecee\Pixie
 */
class QueryBuilderAggregateTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        /*$this->builder->query('TRUNCATE `animal`');

        $qb->from('animal')->insert([
            ['name' => 'mouse', 'number_of_legs' => 28],
            ['name' => 'horse', 'number_of_legs' => 4],
            ['name' => 'cat', 'number_of_legs' => 8],
        ]);*/

    }

    public function testQueryCount()
    {
        $qb = $this->getLiveConnection();

        $count = $qb->from('animal')->select('number_of_legs')->groupBy('number_of_legs')->count();

        $this->assertEquals(3, $count);
        $this->assertEquals('integer', \gettype($count));
    }

    public function testSqliteQueryCount()
    {
        $qb = $this->getLiveConnectionSqlite();

        $count = $qb->from('animal')->select('number_of_legs')->groupBy('number_of_legs')->count();

        $this->assertEquals(3, $count);
        $this->assertEquals('integer', \gettype($count));
    }

    public function testQuerySum()
    {
        $qb = $this->getLiveConnection();

        $count = $qb->from('animal')->select('number_of_legs')->groupBy('number_of_legs')->sum('number_of_legs');

        $this->assertEquals(40, $count);
    }

    public function testSqliteQuerySum()
    {
        $qb = $this->getLiveConnectionSqlite();

        $count = $qb->from('animal')->select('number_of_legs')->groupBy('number_of_legs')->sum('number_of_legs');

        $this->assertEquals(40, $count);
    }

    public function testQueryAverage()
    {
        $qb = $this->getLiveConnection();

        $count = $qb->from('animal')->select('number_of_legs')->groupBy('number_of_legs')->average('number_of_legs');

        $this->assertEquals(13.3333, $count);
    }

    public function testSqliteQueryAverage()
    {
        $qb = $this->getLiveConnectionSqlite();

        $count = $qb->from('animal')->select('number_of_legs')->groupBy('number_of_legs')->average('number_of_legs');

        $this->assertEquals(13.3333333333333, $count);
    }

}