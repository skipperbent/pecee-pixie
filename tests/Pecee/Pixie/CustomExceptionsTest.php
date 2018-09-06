<?php

namespace Pecee\Pixie;

use Pecee\Pixie\Exceptions\ColumnNotFoundException;
use Pecee\Pixie\Exceptions\ConnectionException;
use Pecee\Pixie\Exceptions\DuplicateEntryException;
use Pecee\Pixie\Exceptions\ForeignKeyException;
use Pecee\Pixie\Exceptions\NotNullException;
use Pecee\Pixie\Exceptions\TableNotFoundException;

class CustomExceptionsTest extends TestCase
{

    /**
     * @return \Pecee\Pixie\QueryBuilder\QueryBuilderHandler
     * @throws \Pecee\Pixie\Exception
     */
    public function getQueryBuilder()
    {
        return $this->getLiveConnection();
    }

    protected function validateException(\Exception $exception, $class, ...$codes)
    {
        $this->assertEquals($class, \get_class($exception));

        if ($codes !== null) {
            $this->assertContains($exception->getCode(), $codes, sprintf('Failed asserting exception- expected "%s" got "%s"', implode(' or ', $codes), $exception->getCode()));
        }
    }

    public function testConnectionException()
    {

        // test error code 2002
        try {
            (new \Pecee\Pixie\Connection('mysql', [
                'driver'    => 'mysql',
                'host'      => '0.2.3.4',
                'database'  => 'test',
                'username'  => 'test',
                'password'  => '',
                'charset'   => 'utf8mb4', // Optional
                'collation' => 'utf8mb4_unicode_ci', // Optional
                'prefix'    => '', // Table prefix, optional
            ]))->connect();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, ConnectionException::class, 2002);
        }

        // test error code 1045 - access for user/pass denied to server (wrong username/password)
        try {
            (new \Pecee\Pixie\Connection('mysql', [
                'driver'    => 'mysql',
                'host'      => '127.0.0.1',
                'database'  => 'db',
                'username'  => 'nonexisting',
                'password'  => 'password',
                'charset'   => 'utf8mb4', // Optional
                'collation' => 'utf8mb4_unicode_ci', // Optional
                'prefix'    => '', // Table prefix, optional
            ]))->connect();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, ConnectionException::class, 1045, 2002);
        }

        // test error code 1044 - access to specific DB denied for user
        try {
            (new \Pecee\Pixie\Connection('mysql', [
                'driver'    => 'mysql',
                'host'      => '127.0.0.1',
                'database'  => 'test',
                'username'  => 'nopermuser',
                'password'  => 'nope',
                'charset'   => 'utf8mb4', // Optional
                'collation' => 'utf8mb4_unicode_ci', // Optional
                'prefix'    => '', // Table prefix, optional
            ]))->connect();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {

            // Note: seems like some MySQL instances returns 1044 other 1045.
            $this->validateException($e, ConnectionException::class, 1044, 1045, 2002);
        }

        try {
            (new \Pecee\Pixie\Connection('sqlite', [
                'driver'   => 'sqlite',
                'database' => '/d/c/f',
                'prefix'   => '', // Table prefix, optional
            ]))->connect();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, ConnectionException::class, 1);
        }

    }

    public function testTableNotFoundException()
    {

        $mysqlBuilder = $this->getQueryBuilder();

        try {
            $mysqlBuilder->table('non_existing_table')->where('id', '=', 2)->get();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, TableNotFoundException::class, 1146);
        }

        $sqliteBuilder = $this->getLiveConnectionSqlite();

        try {
            $sqliteBuilder->table('non_existing_table')->where('id', '=', 2)->get();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, TableNotFoundException::class, 1);
        }

    }

    public function testColumnNotFoundException()
    {

        $builder = $this->getQueryBuilder();

        try {
            $builder->table('animal')->where('nonexisting', '=', 2)->get();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, ColumnNotFoundException::class, 1054);
        }

        $sqliteBuilder = $this->getLiveConnectionSqlite();

        try {
            $sqliteBuilder->table('animal')->where('nonexisting', '=', 2)->get();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, ColumnNotFoundException::class, 1);
        }

    }

    public function testDuplicateEntryException()
    {

        $builder = $this->getQueryBuilder();

        try {
            $builder->table('animal')->insert([
                'id' => 1,
            ]);
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, DuplicateEntryException::class, 1062);
        }

        $sqliteBuilder = $this->getLiveConnectionSqlite();

        try {
            $sqliteBuilder->table('animal')->insert([
                'id' => 1,
            ]);
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, DuplicateEntryException::class, 1);
        }

    }

    public function testQueryAggregateColumnNotFoundException()
    {
        $builder = $this->getQueryBuilder();

        try {
            $builder
                ->table('animal')
                ->select(['column1', 'column2', 'column3'])
                ->where('parent_id', 2)
                ->count('column4');
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, ColumnNotFoundException::class, 0);
        }

        $sqliteBuilder = $this->getLiveConnectionSqlite();

        try {
            $sqliteBuilder
                ->table('animal')
                ->select(['column1', 'column2', 'column3'])
                ->where('parent_id', 2)
                ->count('column4');
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, ColumnNotFoundException::class, 0);
        }
    }

    public function testForeignKeyException()
    {
        $builder = $this->getQueryBuilder();

        try {
            $builder
                ->table('tbl_eyes')
                ->where('id', 2)
                ->delete();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, ForeignKeyException::class, 1451);
        }

        $sqliteBuilder = $this->getLiveConnectionSqlite();
        try {
            $sqliteBuilder
                ->table('tbl_eyes')
                ->where('id', 1)
                ->delete();
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, ForeignKeyException::class, 1);
        }
    }

    public function testNotNullException()
    {
        $builder = $this->getQueryBuilder();

        try {
            $builder
                ->table('tbl_eyes')
                ->insert(['color' => null]);
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, NotNullException::class, 1048);
        }

        $sqliteBuilder = $this->getLiveConnectionSqlite();
        try {
            $sqliteBuilder
                ->table('tbl_eyes')
                ->insert(['color' => null]);
            throw new \RuntimeException('check');
        } catch (\Exception $e) {
            $this->validateException($e, NotNullException::class, 1);
        }
    }

}
