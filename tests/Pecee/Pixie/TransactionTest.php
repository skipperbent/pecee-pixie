<?php

namespace Pecee\Pixie;

use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;
use Pecee\Pixie\QueryBuilder\Transaction;

/**
 * Class QueryBuilderTest
 *
 * @package Pecee\Pixie
 */
class TransactionTest extends \PHPUnit\Framework\TestCase {
	/**
	 * @var QueryBuilderHandler
	 */
	private $builder;

	public function setUp() {
		// NOTE: This test will require a live PDO connection

		$connection = new \Pecee\Pixie\Connection('mysql', [
			'driver'    => 'mysql',
			'host'      => '127.0.0.1',
			'database'  => 'test',
			'username'  => 'root',
			'password'  => '123456',
			'charset'   => 'utf8mb4', // Optional
			'collation' => 'utf8mb4_unicode_ci', // Optional
			'prefix'    => '', // Table prefix, optional
		]);

		$this->builder = $connection->getQueryBuilder();

	}

	public function testTransactionResult() {

		$this->builder->statement('TRUNCATE `people`');

		$ids = [];

		$this->builder->transaction(function (Transaction $q) use (&$ids) {

			$ids = $q->table('people')->insert([
				[
					'name'     => 'Simon',
					'age'      => 12,
					'awesome'  => true,
					'nickname' => 'ponylover94',
				],
				[
					'name'     => 'Peter',
					'age'      => 40,
					'awesome'  => false,
					'nickname' => null,
				],
				[
					'name'     => 'Bobby',
					'age'      => 20,
					'awesome'  => true,
					'nickname' => 'peter',
				],
			]);

		});

		$this->assertEquals(1, $ids[0]);
		$this->assertEquals(2, $ids[1]);
		$this->assertEquals(3, $ids[2]);

		$this->assertEquals($this->builder->table('people')->count(), 3);

	}


	/**
	 * @throws Exception
	 */
	public function testNestedTransactions() {

		$this->builder->statement('TRUNCATE `people`; TRUNCATE `animal`');

		function getAnimals() {
			return [
				['name' => 'mouse', 'number_of_legs' => '28'],
				['name' => 'horse', 'number_of_legs' => '4'],
				['name' => 'cat', 'number_of_legs' => '8']
			];
		}

		function getPersons() {
			return
				[
					[
						'name'     => 'Osama',
						'age'      => '2',
						'awesome'  => '1',
						'nickname' => 'jihad4evar',
					],
					[
						'name'     => 'Leila',
						'age'      => '76',
						'awesome'  => '1',
						'nickname' => 'coolcatlady',
					],
					[
						'name'     => 'Henry',
						'age'      => '56',
						'awesome'  => '1',
						'nickname' => 'ponylover95',
					]
				];
		}

		$this->builder->transaction(function (Transaction $qb) {

			function firstTrans(Transaction $oQuery) {

				$oQuery->transaction(function (Transaction $qb) {

					$qb->table('animal')->insert([
						getAnimals()
					]);

				});
			}

			function secondTrans(Transaction $oQuery) {
				$oQuery->transaction(function (\Pecee\Pixie\QueryBuilder\Transaction $qb) {

					$qb->table('people')->insert([
						getPersons()
					]);

				});
			}

			firstTrans($qb);
			secondTrans($qb);

		});


		$animals = $this->builder->table('animal')->select(['name', 'number_of_legs'])->get();
		$persons = $this->builder->table('people')->select(['name', 'age', 'awesome', 'nickname'])->get();

		$originalPersons = getPersons();
		$originalAnimals = getAnimals();

		$this->assertSameSize($persons, $originalPersons);
		$this->assertEquals((array)$persons[0], $originalPersons[0]);
		$this->assertEquals((array)$persons[1], $originalPersons[1]);
		$this->assertEquals((array)$persons[2], $originalPersons[2]);

		$this->assertSameSize($animals, $originalAnimals);
		$this->assertEquals((array)$animals[0], $originalAnimals[0]);
		$this->assertEquals((array)$animals[1], $originalAnimals[1]);
	}

	public function testTransactionMultipleInsert() {
		$this->builder->statement('TRUNCATE `people`');

		$ids = $this->builder->table('people')->insert([
			[
				'name'     => 'Simon',
				'age'      => 12,
				'awesome'  => true,
				'nickname' => 'ponylover94',
			],
			[
				'name'     => 'Peter',
				'age'      => 40,
				'awesome'  => false,
				'nickname' => null,
			],
			[
				'name'     => 'Bobby',
				'age'      => 20,
				'awesome'  => true,
				'nickname' => 'peter',
			],
		]);

		$this->assertEquals(1, $ids[0]);
		$this->assertEquals(2, $ids[1]);
		$this->assertEquals(3, $ids[2]);

		$this->assertEquals($this->builder->table('people')->count(), 3);

	}

}
