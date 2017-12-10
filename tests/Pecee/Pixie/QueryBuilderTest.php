<?php

namespace Pecee\Pixie;

use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;

/**
 * Class QueryBuilder
 *
 * @package Pecee\Pixie
 */
class QueryBuilder extends TestCase {
	/**
	 * @var QueryBuilderHandler
	 */
	protected $builder;

	/**
	 * Setup
	 */
	public function setUp() {
		parent::setUp();

		$this->builder = new QueryBuilderHandler($this->mockConnection);
	}

	public function testFalseBoolWhere() {
		$result = $this->builder->table('test')->where('id', '=', false);
		$this->assertEquals('SELECT * FROM `cb_test` WHERE `id` = 0', $result->getQuery()->getRawSql());
	}

	public function testInsertQueryReturnsIdForInsert() {
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

	public function testInsertQueryReturnsIdForInsertIgnore() {
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

	public function testInsertQueryReturnsNullForIgnoredInsert() {
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

	public function testRawQuery() {
		$query    = 'select * from cb_my_table where id = ? and name = ? and hipster = null';
		$bindings = [5, 'usman', null];
		$queryArr = $this->builder->query($query, $bindings)->get();
		$this->assertEquals(
			[
				$query,
				[5, 'usman', null],
			],
			$queryArr
		);
	}

	public function testNullableWhere() {
		$query = $this->builder->table('person')->where('name', [1, null, 3]);

		$this->assertEquals($query->getQuery()->getRawSql(), 'SELECT * FROM `cb_person` WHERE `name` = (1, NULL, 3)');

	}

}
