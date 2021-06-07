<?php

namespace Pecee\Pixie;

use Pecee\Pixie\Event\EventArguments;
use Pecee\Pixie\QueryBuilder\JoinBuilder;

/**
 * Class QueryBuilderTest
 *
 * @package Pecee\Pixie
 */
class QueryBuilderTest extends TestCase
{
    /**
     * Test alias
     */
    public function testAlias()
    {
        $query = $this->builder
            ->table(['table1'])
            ->alias('t1')
            ->innerJoin('table2', 'table2.person_id', '=', 'foo2.id');

        $this->assertEquals('SELECT * FROM `cb_table1` AS `t1` INNER JOIN `cb_table2` ON `cb_table2`.`person_id` = `cb_foo2`.`id`',
            $query->getQuery()->getRawSql());
    }

    /**
     * Test delete
     */
    public function testDeleteQuery()
    {
        $this->builder = new QueryBuilder\QueryBuilderHandler($this->mockConnection);

        $builder = $this->builder->table('my_table')->where('value', '=', 'Amrin');

        $this->assertEquals("DELETE FROM `cb_my_table` WHERE `value` = 'Amrin'"
            , $builder->getQuery('delete')->getRawSql());
    }

    public function testEventPropagation()
    {
        $builder = $this->builder;

        $events = [
            'before-insert',
            'after-insert',
            'before-select',
            'after-select',
            'before-update',
            'after-update',
            'before-delete',
            'after-delete',
        ];

        $triggeredEvents = [];

        foreach ($events as $event) {
            $builder->registerEvent($event, function ($qb) use (&$triggeredEvents, $event) {
                $triggeredEvents[] = $event;
            }, ':any');
        }

        $builder->table('foo')->insert(['bar' => 'baz']);
        $builder->from('foo')->select('bar')->get();
        $builder->table('foo')->update(['bar' => 'baz']);
        $builder->from('foo')->delete();

        $this->assertEquals($triggeredEvents, $events);
    }

    public function testInsertIgnoreQuery()
    {
        $builder = $this->builder->from('my_table');
        $data = [
            'key'   => 'Name',
            'value' => 'Sana',
        ];

        $this->assertEquals("INSERT IGNORE INTO `cb_my_table` (`key`, `value`) VALUES ('Name', 'Sana')"
            , $builder->getQuery('insertignore', $data)->getRawSql());
    }

    public function testInsertOnDuplicateKeyUpdateQuery()
    {
        $builder = $this->builder;
        $data = [
            'name'    => 'Sana',
            'counter' => 1,
        ];
        $dataUpdate = [
            'name'    => 'Sana',
            'counter' => 2,
        ];
        $builder->from('my_table')->onDuplicateKeyUpdate($dataUpdate);
        $this->assertEquals("INSERT INTO `cb_my_table` (`name`, `counter`) VALUES ('Sana', 1) ON DUPLICATE KEY UPDATE `name` = 'Sana', `counter` = 2"
            , $builder->getQuery('insert', $data)->getRawSql());
    }

    public function testInsertQuery()
    {
        $builder = $this->builder->from('my_table');
        $data = [
            'key'   => 'Name',
            'value' => 'Sana',
        ];

        $this->assertEquals("INSERT INTO `cb_my_table` (`key`, `value`) VALUES ('Name', 'Sana')"
            , $builder->getQuery('insert', $data)->getRawSql());
    }

    public function testIsPossibleToUseSubqueryInWhereClause()
    {
        $sub = clone $this->builder;
        $query = $this->builder->from('my_table')->whereIn('foo', $this->builder->subQuery(
            $sub->from('some_table')->select('foo')->where('id', 1)
        ));
        $this->assertEquals(
            "SELECT * FROM `cb_my_table` WHERE `foo` IN (SELECT `foo` FROM `cb_some_table` WHERE `id` = 1)",
            $query->getQuery()->getRawSql()
        );
    }

    public function testIsPossibleToUseSubqueryInWhereNotClause()
    {
        $sub = clone $this->builder;
        $query = $this->builder->from('my_table')->whereNotIn('foo', $this->builder->subQuery(
            $sub->from('some_table')->select('foo')->where('id', 1)
        ));
        $this->assertEquals(
            "SELECT * FROM `cb_my_table` WHERE `foo` NOT IN (SELECT `foo` FROM `cb_some_table` WHERE `id` = 1)",
            $query->getQuery()->getRawSql()
        );
    }

    public function testOrderByFlexibility()
    {
        $query = $this->builder
            ->from('t')
            ->orderBy('foo', 'DESC')
            ->orderBy(['bar', 'baz' => 'ASC', $this->builder->raw('raw1')], 'DESC')
            ->orderBy($this->builder->raw('raw2'), 'DESC');

        $this->assertEquals(
            'SELECT * FROM `cb_t` ORDER BY `foo` DESC, `bar` DESC, `baz` ASC, raw1 DESC, raw2 DESC',
            $query->getQuery()->getRawSql(),
            'ORDER BY is flexible enough!'
        );
    }

    public function testRawStatementsWithinCriteria()
    {
        $query = $this->builder->from('my_table')
            ->where('simple', 'criteria')
            ->where($this->builder->raw('RAW'))
            ->where($this->builder->raw('PARAMETERIZED_ONE(?)', 'foo'))
            ->where($this->builder->raw('PARAMETERIZED_SEVERAL(?, ?, ?)', [1, '2', 'foo']));

        $this->assertEquals(
            "SELECT * FROM `cb_my_table` WHERE `simple` = 'criteria' AND RAW AND PARAMETERIZED_ONE('foo') AND PARAMETERIZED_SEVERAL(1, '2', 'foo')",
            $query->getQuery()->getRawSql()
        );
    }

    public function testRawStatementWithinStatement() {
        $query = $this->builder->from('my_table')
            ->where('simple', '=', 'criteria')
            ->update([
                'alias' => $this->builder->raw('?', ['2'])
            ]);

        $this->assertEquals(
            "UPDATE `cb_my_table` SET `alias` = '2' WHERE `simple` = 'criteria'",
            $this->builder->getLastQuery()->getRawSql()
        );
    }

    public function testRawStatementWithinSelect() {
        $this->builder->from('my_table')
            ->select($this->builder->raw('CONCAT(`simple`, ?)', ['criteria']))
            ->where('simple', '=', 'criteria')
            ->first();

        $this->assertEquals(
            "SELECT CONCAT(`simple`, 'criteria') FROM `cb_my_table` WHERE `simple` = 'criteria' LIMIT 1",
            $this->builder->getLastQuery()->getRawSql()
        );
    }

    public function testRawStatementWithinJoin() {
        $this->builder->from('my_table')
            ->join('people', 'id', '=', $this->builder->raw("CONCAT('hej', ?)", ['shemales']))
            ->where('simple', '=', 'criteria')
            ->first();

        $this->assertEquals(
            "SELECT * FROM `cb_my_table` JOIN `cb_people` ON `id` = CONCAT('hej', 'shemales') WHERE `simple` = 'criteria' LIMIT 1",
            $this->builder->getLastQuery()->getRawSql()
        );
    }

    public function testRawExpression() {
        $query = $this->builder
            ->table('my_table')
            ->alias('test')
            ->select($this->builder->raw('count(cb_my_table.id) as tot'))
            ->where('value', '=', 'Ifrah')
            ->where($this->builder->raw('DATE(?)', 'now'));

        $this->assertEquals("SELECT count(cb_my_table.id) as tot FROM `cb_my_table` AS `test` WHERE test.`value` = 'Ifrah' AND DATE('now')", $query->getQuery()->getRawSql());
    }

    public function testReplaceQuery()
    {
        $builder = $this->builder->from('my_table');
        $data = [
            'key'   => 'Name',
            'value' => 'Sana',
        ];

        $this->assertEquals("REPLACE INTO `cb_my_table` (`key`, `value`) VALUES ('Name', 'Sana')"
            , $builder->getQuery('replace', $data)->getRawSql());
    }

    public function testSelectAliases()
    {
        $query = $this->builder->from('my_table')->select('foo')->select(['bar' => 'baz', 'qux']);

        $this->assertEquals(
            "SELECT `foo`, `bar` AS `baz`, `qux` FROM `cb_my_table`",
            $query->getQuery()->getRawSql()
        );
    }

    public function testSelectDistinct()
    {
        $query = $this->builder->selectDistinct(['name', 'surname'])->from('my_table');
        $this->assertEquals("SELECT DISTINCT `name`, `surname` FROM `cb_my_table`", $query->getQuery()->getRawSql());
    }

    public function testSelectDistinctAndSelectCalls()
    {
        $query = $this->builder->select('name')->selectDistinct('surname')->select([
            'birthday',
            'address',
        ])->from('my_table');
        $this->assertEquals("SELECT DISTINCT `surname`, `name`, `birthday`, `address` FROM `cb_my_table`", $query->getQuery()->getRawSql());
    }

    public function testSelectDistinctWithSingleColumn()
    {
        $query = $this->builder->selectDistinct('name')->from('my_table');
        $this->assertEquals("SELECT DISTINCT `name` FROM `cb_my_table`", $query->getQuery()->getRawSql());
    }

    public function testSelectFlexibility()
    {
        $query = $this->builder
            ->select('foo')
            ->select(['bar', 'baz'])
            ->select('qux', 'lol', 'wut')
            ->from('t');
        $this->assertEquals(
            'SELECT `foo`, `bar`, `baz`, `qux`, `lol`, `wut` FROM `cb_t`',
            $query->getQuery()->getRawSql(),
            'SELECT is pretty flexible!'
        );
    }

    public function testSelectQuery()
    {
        $subQuery = $this->builder->table('person_details')->select('details')->where('person_id', '=', 3);

        $query = $this->builder->table('my_table')
            ->select('my_table.*')
            ->select([
                $this->builder->raw('count(cb_my_table.id) AS `tot`'),
                $this->builder->subQuery($subQuery, 'pop'),
            ])
            ->where('value', '=', 'Ifrah')
            ->whereNot('my_table.id', -1)
            ->orWhereNot('my_table.id', -2)
            ->orWhereIn('my_table.id', [1, 2])
            ->groupBy(['value', 'my_table.id', 'person_details.id'])
            ->orderBy('my_table.id', 'DESC')
            ->orderBy('value')
            ->having('tot', '<', 2)
            ->limit(1)
            ->offset(0)
            ->innerJoin(
                'person_details',
                'person_details.person_id',
                '=',
                'my_table.id'
            );

        $nestedQuery = $this->builder->table($this->builder->subQuery($query, 'bb'))->select('*');
        $this->assertEquals("SELECT * FROM (SELECT `cb_my_table`.*, count(cb_my_table.id) AS `tot`, (SELECT `details` FROM `cb_person_details` WHERE `person_id` = 3) AS `pop` FROM `cb_my_table` INNER JOIN `cb_person_details` ON `cb_person_details`.`person_id` = `cb_my_table`.`id` WHERE `value` = 'Ifrah' AND NOT `cb_my_table`.`id` = -1 OR NOT `cb_my_table`.`id` = -2 OR `cb_my_table`.`id` IN (1, 2) GROUP BY `value`, `cb_my_table`.`id`, `cb_person_details`.`id` HAVING `tot` < 2 ORDER BY `cb_my_table`.`id` DESC, `value` ASC LIMIT 1 OFFSET 0) AS `bb`"
            , $nestedQuery->getQuery()->getRawSql());
    }

    public function testSelectQueryWithNestedCriteriaAndJoins()
    {
        $builder = $this->builder;

        $query = $builder->table('my_table')
            ->where('my_table.id', '>', 1)
            ->orWhere('my_table.id', 1)
            ->where(function ($q) {
                $q->where('value', 'LIKE', '%sana%');
                $q->orWhere(function ($q2) {
                    $q2->where('key', 'LIKE', '%sana%');
                    $q2->orWhere('value', 'LIKE', '%sana%');
                });
            })
            ->innerJoin(['person_details', 'a'], 'a.person_id', '=', 'my_table.id')
            ->leftJoin(['person_details', 'b'], function ($table) use ($builder) {
                $table->on('b.person_id', '=', 'my_table.id');
                $table->on('b.deleted', '=', $builder->raw(0));
                $table->orOn('b.age', '>', $builder->raw(1));
            });

        $this->assertEquals("SELECT * FROM `cb_my_table` INNER JOIN `cb_person_details` AS `cb_a` ON `cb_a`.`person_id` = `cb_my_table`.`id` LEFT JOIN `cb_person_details` AS `cb_b` ON `cb_b`.`person_id` = `cb_my_table`.`id` AND `cb_b`.`deleted` = 0 OR `cb_b`.`age` > 1 WHERE `cb_my_table`.`id` > 1 OR `cb_my_table`.`id` = 1 AND (`value` LIKE '%sana%' OR (`key` LIKE '%sana%' OR `value` LIKE '%sana%'))"
            , $query->getQuery()->getRawSql());
    }

    public function testSelectQueryWithNull()
    {
        $query = $this->builder->from('my_table')
            ->whereNull('key1')
            ->orWhereNull('key2')
            ->whereNotNull('key3')
            ->orWhereNotNull('key4')
            ->orWhere('key5', '=', null);

        $this->assertEquals(
            "SELECT * FROM `cb_my_table` WHERE `key1` IS NULL OR `key2` IS NULL AND `key3` IS NOT NULL OR `key4` IS NOT NULL OR `key5` = NULL",
            $query->getQuery()->getRawSql()
        );
    }

    public function testSelectWithQueryEvents()
    {
        $builder = $this->builder;

        $builder->registerEvent('before-select', function (EventArguments $data) {
            $data->getQueryBuilder()->whereIn('status', [1, 2]);
        }, ':any');

        $query = $builder->table('some_table')->where('name', 'Some');
        $query->get();
        $actual = $query->getQuery()->getRawSql();

        $this->assertEquals("SELECT * FROM `cb_some_table` WHERE `name` = 'Some' AND `status` IN (1, 2)", $actual);
    }

    public function testStandaloneWhereNot()
    {
        $query = $this->builder->table('my_table')->whereNot('foo', 1);
        $this->assertEquals("SELECT * FROM `cb_my_table` WHERE NOT `foo` = 1", $query->getQuery()->getRawSql());
    }

    public function testUpdateQuery()
    {
        $builder = $this->builder->table('my_table')->where('value', 'Sana');

        $data = [
            'key'   => 'Sana',
            'value' => 'Amrin',
        ];

        $this->assertEquals("UPDATE `cb_my_table` SET `key` = 'Sana', `value` = 'Amrin' WHERE `value` = 'Sana'"
            , $builder->getQuery('update', $data)->getRawSql());
    }

    /**
     * Test delete query with all statements.
     * Note: the statement might not be valid as some statements can't be used together.
     *
     * @throws Exception
     */
    public function testDeleteAdvancedQuery()
    {

        $this->builder
            ->table('foo')
            ->leftJoin('bar', 'foo.id', '=', 'bar.id')
            ->where('bar.id', 1)
            ->groupBy(['foo.id'])
            ->orderBy('foo.id')
            ->limit(1)
            ->offset(1)
            ->delete(['foo.status']);

        $this->assertEquals(
            'DELETE `foo`.`status` FROM `cb_foo` LEFT JOIN `cb_bar` ON `cb_foo`.`id` = `cb_bar`.`id` WHERE `cb_bar`.`id` = 1 GROUP BY `cb_foo`.`id` ORDER BY `cb_foo`.`id` ASC LIMIT 1 OFFSET 1',
            $this->builder->getConnection()->getLastQuery()->getRawSql());
    }

    /**
     * Test update query with all statements.
     * Note: the statement might not be valid as some statements can't be used together.
     *
     * @throws Exception
     */
    public function testUpdateAdvancedQuery()
    {

        $this->builder
            ->table('foo')
            ->leftJoin('bar', 'foo.id', '=', 'bar.id')
            ->where('bar.id', 1)
            ->groupBy(['foo.id'])
            ->orderBy('foo.id')
            ->limit(1)
            ->offset(1)
            ->update(['foo.status' => 1]);

        $this->assertEquals(
            'UPDATE `cb_foo` LEFT JOIN `cb_bar` ON `cb_foo`.`id` = `cb_bar`.`id` SET `foo`.`status` = 1 WHERE `cb_bar`.`id` = 1 GROUP BY `cb_foo`.`id` ORDER BY `cb_foo`.`id` ASC LIMIT 1 OFFSET 1',
            $this->builder->getConnection()->getLastQuery()->getRawSql());
    }

    public function testFromSubQuery()
    {

        $subQuery = $this->builder->table('person');
        $builder = $this->builder->table($this->builder->subQuery($subQuery))->where('id', '=', 2);

        $this->assertEquals('SELECT * FROM (SELECT * FROM `cb_person`) WHERE `id` = 2', $builder->getQuery()->getRawSql());

    }

    public function testTableAlias()
    {

        $builder = $this->builder->table('persons')->alias('staff');

        $this->assertEquals('SELECT * FROM `cb_persons` AS `staff`', $builder->getQuery()->getRawSql());

    }

    public function testWhereNotNullSubQuery()
    {
        $subQuery = $this->builder->table('persons')->alias('staff');

        $query = $this->builder->whereNull($this->builder->subQuery($subQuery));

        $this->assertEquals('SELECT * WHERE (SELECT * FROM `cb_persons` AS `staff`) IS NULL', $query->getQuery()->getRawSql());

    }

    public function testJoinUsing()
    {

        $query = $this->builder->table('user')->joinUsing('user_data', ['user_id', 'image_id'])->where('user_id', '=', 1);

        $this->assertEquals('SELECT * FROM `cb_user` JOIN `cb_user_data` USING (`user_id`, `image_id`) WHERE `user_id` = 1', $query->getQuery()->getRawSql());

    }

    public function testJoinQueryBuilderUsing()
    {

        $query = $this->builder->table('user')->join('user_data', function (JoinBuilder $jb) {
            $jb->using(['user_id', 'image_id']);
        })->where('user_id', '=', 1);

        $this->assertEquals('SELECT * FROM `cb_user` JOIN `cb_user_data` USING (`user_id`, `image_id`) WHERE `user_id` = 1', $query->getQuery()->getRawSql());

    }

}