<?php namespace Pecee\Pixie;

use Mockery as m;
use Pecee\Pixie\ConnectionAdapters\IConnectionAdapter;
use Pecee\Pixie\ConnectionAdapters\Mysql;
use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;

/**
 * Class ConnectionTest
 *
 * @package Pecee\Pixie
 */
class ConnectionTest extends TestCase
{
    /**
     * @var \Mockery\Mock
     */
    private $mysqlConnectionMock;
    /**
     * @var \Pecee\Pixie\Connection
     */
    private $connection;

    /**
     * @var QueryBuilderHandler
     */
    protected $builder;

    public function setUp()
    {
        parent::setUp();

        $this->mysqlConnectionMock = m::mock(Mysql::class);
        $this->mysqlConnectionMock->shouldReceive('connect')->andReturn($this->mockPdo);

        $this->connection = new Connection($this->mysqlConnectionMock, ['prefix' => 'cb_']);
    }

    public function testConnection()
    {
        $this->connection->connect();
        $this->assertEquals($this->mockPdo, $this->connection->getPdoInstance());
        $this->assertInstanceOf(\PDO::class, $this->connection->getPdoInstance());
        $this->assertInstanceOf(IConnectionAdapter::class, $this->connection->getAdapter());
        $this->assertEquals(['prefix' => 'cb_'], $this->connection->getAdapterConfig());
    }
}
