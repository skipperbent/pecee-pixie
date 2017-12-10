<?php namespace Pecee\Pixie;

use Mockery as m;
use Pecee\Pixie\ConnectionAdapters\IConnectionAdapter;
use Pecee\Pixie\ConnectionAdapters\Mysql;

class ConnectionTest extends TestCase
{
    private $mysqlConnectionMock;
    private $connection;

    public function setUp()
    {
        parent::setUp();

        $this->mysqlConnectionMock = m::mock(Mysql::class);
        $this->mysqlConnectionMock->shouldReceive('connect')->andReturn($this->mockPdo);

        $this->connection = new Connection($this->mysqlConnectionMock, array('prefix' => 'cb_'));
    }

    public function testConnection()
    {
        $this->assertEquals($this->mockPdo, $this->connection->getPdoInstance());
        $this->assertInstanceOf(\PDO::class, $this->connection->getPdoInstance());
        $this->assertInstanceOf(IConnectionAdapter::class, $this->connection->getAdapter());
        $this->assertEquals(array('prefix' => 'cb_'), $this->connection->getAdapterConfig());
    }
}