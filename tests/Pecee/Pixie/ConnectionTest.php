<?php namespace Pecee\Pixie;

use Mockery as m;
use Pecee\Pixie\ConnectionAdapters\Mysql;
use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;

class ConnectionTest extends TestCase
{
    private $mysqlConnectionMock;
    private $connection;

    public function setUp()
    {
        parent::setUp();

        $this->mysqlConnectionMock = m::mock(Mysql::class);
        $this->mysqlConnectionMock->shouldReceive('connect')->andReturn($this->mockPdo);

        $this->container->setInstance('\Pecee\Pixie\ConnectionAdapters\Mysqlmock', $this->mysqlConnectionMock);
        $this->connection = new Connection('mysqlmock', array('prefix' => 'cb_'), $this->container);
    }

    public function testConnection()
    {
        $this->assertEquals($this->mockPdo, $this->connection->getPdoInstance());
        $this->assertInstanceOf('\PDO', $this->connection->getPdoInstance());
        $this->assertEquals('mysqlmock', $this->connection->getAdapter());
        $this->assertEquals(array('prefix' => 'cb_'), $this->connection->getAdapterConfig());
    }
}