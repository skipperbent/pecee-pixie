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

        $this->assertEquals('SELECT * FROM `cb_person` WHERE `name` = (1, NULL, 3)', $query->getQuery()->getRawSql());

    }

    public function testWhereBetween()
    {
        $qb = $this->builder;
        $query = $qb->table('animals')->whereBetween('created_date', $qb->raw('NOW()'), '27-05-2017');

        $this->assertEquals('SELECT * FROM `cb_animals` WHERE `created_date` BETWEEN NOW() AND \'27-05-2017\'', $query->getQuery()->getRawSql());
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

    public function testQueryOverwrite()
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
  
    /**
     * @throws \Pecee\Pixie\Exception
     */
    public function testGetColumns(){
        $query = $this->builder
            ->newQuery()
            ->table(['foo_table','foo'])
            ->leftJoin(['bar_table','bar'],'foo._barId','=','bar.id')
            ->leftJoin(['baz_table','baz'],'bar._bazId','=','baz.id')
            ->select([
                'foo.*',
                'bar.id'=>'barId',
                'name',
                $this->builder->raw('baz.name as bazName')
            ])
        ;
        $this->assertEquals([
            'barId' =>  'cb_bar.id',
            'name'  =>  'name'
        ],$query->getColumns());
    }

    public function testQueryPartFor()
    {
        $query = $this->builder->newQuery()->select(['id'])->table('users')->for('UPDATE');

        $this->assertEquals('SELECT * FROM `cb_users` FOR UPDATE', $query->getQuery()->getRawSql());
    }

    public function testRemoveQuery() {

        $builder = $this->builder->newQuery()->table('test')->setOverwriteEnabled(true);

        $query = $builder
            ->where('parent_id', '=', 10)
            ->where('parent_id', '=', 5)
            ->limit(5);

        $this->assertEquals('SELECT * FROM `cb_test` WHERE `parent_id` = 5 LIMIT 5', $query->getQuery()->getRawSql());

        $query = $builder->whereNull('parent_id');

        $this->assertEquals('SELECT * FROM `cb_test` WHERE `parent_id` IS NULL LIMIT 5', $query->getQuery()->getRawSql());

    }

}