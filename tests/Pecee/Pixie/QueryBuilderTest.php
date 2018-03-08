<?php

namespace Pecee\Pixie;

/**
 * Class QueryBuilder
 *
 * @package Pecee\Pixie
 */
class QueryBuilder extends TestCase
{

    public function testFalseBoolWhere()
    {
        $result = $this->builder->table('test')->where('id', '=', false);
        $this->assertEquals('SELECT * FROM `cb_test` WHERE `id` = 0', $result->getQuery()->getRawSql());
    }

    public function testInsertQueryReturnsIdForInsert()
    {
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue(1));

        $this->mockPdo
            ->expects($this->once())
            ->method('lastInsertId')
            ->will($this->returnValue(11));

        $id = $this->builder->table('test')->insert([
            'id'   => 5,
            'name' => 'usman',
        ]);

        $this->assertEquals(11, $id);
    }

    public function testInsertQueryReturnsIdForInsertIgnore()
    {
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue(1));

        $this->mockPdo
            ->expects($this->once())
            ->method('lastInsertId')
            ->will($this->returnValue(11));

        $id = $this->builder->table('test')->insertIgnore([
            'id'   => 5,
            'name' => 'usman',
        ]);

        $this->assertEquals(11, $id);
    }

    public function testInsertQueryReturnsNullForIgnoredInsert()
    {
        $this->mockPdoStatement
            ->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue(0));

        $id = $this->builder->table('test')->insertIgnore([
            'id'   => 5,
            'name' => 'usman',
        ]);

        $this->assertEquals(null, $id);
    }

    public function testRawQuery()
    {
        $query = 'select * from cb_my_table where id = ? and name = ? and hipster = null';
        $bindings = [5, 'usman', null];
        $queryArr = $this->builder->query($query, $bindings)->get();

        $this->assertEquals(
            [
                $query,
                [[5, \PDO::PARAM_INT], ['usman', \PDO::PARAM_STR], [null, \PDO::PARAM_NULL]],
            ],
            $queryArr
        );
    }

    public function testNullableWhere()
    {
        $query = $this->builder->table('person')->where('name', [1, null, 3]);

        $this->assertEquals($query->getQuery()->getRawSql(), 'SELECT * FROM `cb_person` WHERE `name` = (1, NULL, 3)');

    }

    public function testWhereBetween()
    {

        $qb = $this->builder;
        $query = $qb->table('animals')->whereBetween('created_date', $qb->raw('NOW()'), '27-05-2017');

        $this->assertEquals($query->getQuery()->getRawSql(), 'SELECT * FROM `cb_animals` WHERE `created_date` BETWEEN NOW() AND \'27-05-2017\'');

    }

    public function testUnion()
    {

        $qb = $this->builder;
        $firstQuery =
            $qb
                ->table('people')
                ->whereNull('email');

        $secondQuery =
            $qb
                ->table('people')
                ->where('hair_color', '=', 'green')
                ->union($firstQuery);

        $thirdQuery =
            $qb
                ->table('people')
                ->where('gender', '=', 'male')
                ->union($secondQuery);

        $this->assertEquals(
            '(SELECT * FROM `cb_people` WHERE `gender` = \'male\') UNION (SELECT * FROM `cb_people` WHERE `email` IS NULL) UNION (SELECT * FROM `cb_people` WHERE `hair_color` = \'green\')',
            $thirdQuery->getQuery()->getRawSql()
        );
    }

    public function testUnionSubQuery()
    {
        $qb = $this->builder;
        $first = $qb->table('people')->whereNull('name');
        $second = $qb->table('people')->where('gender', '=', 'male')->union($first);

        $main = $qb->table($qb->subQuery($second, 'people'))->select(['id', 'name']);

        $this->assertEquals(
            'SELECT `id`, `name` FROM ((SELECT * FROM `cb_people` WHERE `gender` = \'male\') UNION (SELECT * FROM `cb_people` WHERE `name` IS NULL)) AS `people`',
            $main->getQuery()->getRawSql()
        );

    }

    public function testQueryCount()
    {
        $qb = $this->getLiveConnection();

        $count = $qb->from('animal')->groupBy('number_of_legs')->count();

        $this->assertEquals(3, $count);
    }

    public function testQuerySum()
    {
        $qb = $this->getLiveConnection();

        $count = $qb->from('animal')->groupBy('number_of_legs')->sum('number_of_legs');

        $this->assertEquals(40, $count);
    }

    public function testQueryAverage()
    {
        $qb = $this->getLiveConnection();

        $count = $qb->from('animal')->groupBy('number_of_legs')->average('number_of_legs');

        $this->assertEquals(13.3333, $count);
    }

}
