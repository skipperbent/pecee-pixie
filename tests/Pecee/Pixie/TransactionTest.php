<?php

namespace Pecee\Pixie;

use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;
use Pecee\Pixie\QueryBuilder\Transaction;

/**
 * Class QueryBuilderTest
 *
 * @package Pecee\Pixie
 */
class TransactionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var QueryBuilderHandler
     */
    private $builder;

    public function setUp()
    {
        // NOTE: This test will require a live PDO connection

        $connection = new \Pecee\Pixie\Connection('mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'test',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4', // Optional
            'collation' => 'utf8mb4_unicode_ci', // Optional
            'prefix' => '', // Table prefix, optional
        ]);

        $this->builder = $connection->getQueryBuilder();

    }

    public function testTransactionResult() {

        $this->builder->statement('TRUNCATE `people`');

        $ids = [];

        $this->builder->transaction(function(Transaction $q) use(&$ids) {

            $ids = $q->table('people')->insert([
                [
                    'name' => 'Simon',
                    'age' => 12,
                    'awesome' => true,
                    'nickname' => 'ponylover94',
                ],
                [
                    'name' => 'Peter',
                    'age' => 40,
                    'awesome' => false,
                    'nickname' => null,
                ],
                [
                    'name' => 'Bobby',
                    'age' => 20,
                    'awesome' => true,
                    'nickname' => 'peter',
                ],
            ]);

        });

        $this->assertEquals(1, $ids[0]);
        $this->assertEquals(2, $ids[1]);
        $this->assertEquals(3, $ids[2]);

        $this->assertEquals($this->builder->table('people')->count(), 3);

    }

}
