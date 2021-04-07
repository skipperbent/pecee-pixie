<?php

namespace Pecee\Pixie;

use Mockery as m;
use Pecee\Pixie\ConnectionAdapters\Mysql;
use Pecee\Pixie\Event\EventHandler;
use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;

/**
 * Class TestCase
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Mockery\Mock
     */
    protected $mockConnection;
    /**
     * @var \PDO
     */
    protected $mockPdo;
    /**
     * @var \Mockery\Mock
     */
    protected $mockPdoStatement;

    /**
     * @var QueryBuilderHandler
     */
    protected $builder;

    /**
     * @return array
     */
    public function callbackMock()
    {
        $args = func_get_args();

        return 1 == count($args) ? $args[0] : $args;
    }

    public function setUp()
    {
        $this->mockPdoStatement = $this->getMockBuilder(\PDOStatement::class)->getMock();

        $mockPdoStatement = &$this->mockPdoStatement;

        $mockPdoStatement->bindings = [];

        $this->mockPdoStatement
            ->expects($this->any())
            ->method('bindValue')
            ->will($this->returnCallback(function ($parameter, $value, $dataType) use ($mockPdoStatement) {
                $mockPdoStatement->bindings[] = [$value, $dataType];
            }));

        $this->mockPdoStatement
            ->expects($this->any())
            ->method('execute')
            ->will($this->returnCallback(function ($bindings = null) use ($mockPdoStatement) {
                if ($bindings) {
                    $mockPdoStatement->bindings = $bindings;
                }
            }));

        $this->mockPdoStatement
            ->expects($this->any())
            ->method('fetchAll')
            ->will($this->returnCallback(function () use ($mockPdoStatement) {
                return [$mockPdoStatement->sql, $mockPdoStatement->bindings];
            }));

        $this->mockPdo = $this
            ->getMockBuilder(MockPdo::class)
            ->setMethods(['prepare', 'setAttribute', 'quote', 'lastInsertId'])
            ->getMock();

        $this->mockPdo
            ->expects($this->any())
            ->method('prepare')
            ->will($this->returnCallback(function ($sql) use ($mockPdoStatement) {
                $mockPdoStatement->sql = $sql;

                return $mockPdoStatement;
            }));

        $this->mockPdo
            ->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function ($value) {
                return "'$value'";
            }));

        $eventHandler = new EventHandler();

        $this->mockConnection = m::mock(Connection::class);
        $this->mockConnection->shouldReceive('getPdoInstance')->andReturn($this->mockPdo);
        $this->mockConnection->shouldReceive('getAdapter')->andReturn(new Mysql());
        $this->mockConnection->shouldReceive('getAdapterConfig')->andReturn(['prefix' => 'cb_']);
        $this->mockConnection->shouldReceive('getEventHandler')->andReturn($eventHandler);
        $this->mockConnection->shouldReceive('setLastQuery')->passthru();
        $this->mockConnection->shouldReceive('getLastQuery')->passthru();
        $this->mockConnection->shouldReceive('connect')->andReturn($this->mockConnection);
        $this->builder = new QueryBuilderHandler($this->mockConnection);
    }

    /**
     * @throws \Pecee\Pixie\Exception
     *
     * @return \Pecee\Pixie\QueryBuilder\QueryBuilderHandler
     */
    public function getLiveConnection()
    {
        $connection = new \Pecee\Pixie\Connection('mysql', [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'database'  => 'test',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8mb4', // Optional
            'collation' => 'utf8mb4_unicode_ci', // Optional
            'prefix'    => '', // Table prefix, optional
        ]);

        $qb = $connection->getQueryBuilder();
        $qb->pdo()->exec(file_get_contents(__DIR__.'/db_mysql.sql'));
        $qb->pdo()->exec(file_get_contents(__DIR__.'/db_values.sql'));

        return $qb->newQuery();
    }

    /**
     * @throws \Pecee\Pixie\Exception
     *
     * @return \Pecee\Pixie\QueryBuilder\QueryBuilderHandler
     */
    public function getLiveConnectionSqlite()
    {
        $connection = new \Pecee\Pixie\Connection('sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            //'database' => __DIR__.'/db_sqlite.sqlite',
            'prefix'   => '',
        ]);

        $qb = $connection->getQueryBuilder();
        $qb->pdo()->exec(file_get_contents(__DIR__.'/db_sqlite.sql'));
        $qb->pdo()->exec(file_get_contents(__DIR__.'/db_values.sql'));

        return $qb->newQuery();
    }

    public function tearDown()
    {
        m::close();
    }
}

/**
 * Class MockPdo
 */
class MockPdo extends \PDO
{
    /**
     * MockPdo constructor.
     */
    public function __construct()
    {
    }
}
