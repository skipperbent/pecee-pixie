<?php

namespace Pecee\Pixie;

use Pecee\Pixie\Exceptions\ColumnNotFoundException;
use Pecee\Pixie\Exceptions\ConnectionException;
use Pecee\Pixie\Exceptions\DuplicateEntryException;
use Pecee\Pixie\Exceptions\TableNotFoundException;

class CustomExceptionsTest extends TestCase
{

    public function getQueryBuilder()
    {
        $con = new \Pecee\Pixie\Connection('mysql', [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'database'  => 'test',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8mb4', // Optional
            'collation' => 'utf8mb4_unicode_ci', // Optional
            'prefix'    => '', // Table prefix, optional
        ]);

        return $con->getQueryBuilder();
    }

    protected function validateException(\Exception $exception, $class, $code = null)
    {

        if ($code !== null) {
            $this->assertEquals($code, $exception->getCode());
        }

        $this->assertEquals($class, \get_class($exception));
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
        } catch (\Exception $e) {
            $this->validateException($e, ConnectionException::class, 2002);
        }

        // test error code 1045
        try {
            (new \Pecee\Pixie\Connection('mysql', [
                'driver'    => 'mysql',
                'host'      => '127.0.0.1',
                'database'  => 'test',
                'username'  => 'root',
                'password'  => 'asdasdasd',
                'charset'   => 'utf8mb4', // Optional
                'collation' => 'utf8mb4_unicode_ci', // Optional
                'prefix'    => '', // Table prefix, optional
            ]))->connect();
        } catch (\Exception $e) {
            $this->validateException($e, ConnectionException::class, 1045);
        }

        // test error code 1044
        try {
            (new \Pecee\Pixie\Connection('mysql', [
                'driver'    => 'mysql',
                'host'      => '127.0.0.1',
                'database'  => 'root',
                'username'  => 'nonexisting',
                'password'  => '',
                'charset'   => 'utf8mb4', // Optional
                'collation' => 'utf8mb4_unicode_ci', // Optional
                'prefix'    => '', // Table prefix, optional
            ]))->connect();
        } catch (\Exception $e) {
            $this->validateException($e, ConnectionException::class, 1044);
        }

    }

    public function testTableNotFoundException()
    {

        $builder = $this->getQueryBuilder();

        try {
            $builder->table('hello')->where('id', '=', 2)->get();
        } catch (\Exception $e) {
            $this->validateException($e, TableNotFoundException::class, 1146);
        }

    }

    public function testColumnNotFoundException()
    {

        $builder = $this->getQueryBuilder();

        try {
            $builder->table('animal')->where('nonexisting', '=', 2)->get();
        } catch (\Exception $e) {
            $this->validateException($e, ColumnNotFoundException::class, 1054);
        }

    }

    public function testDuplicateEntryException()
    {

        $builder = $this->getQueryBuilder();

        try {
            $builder->table('animal')->insert([
                'id' => 1,
            ]);
        } catch (\Exception $e) {
            $this->validateException($e, DuplicateEntryException::class, 1062);
        }

    }

    public function testQueryAggregateColumnNotFoundException()
    {
        $this->expectException(ColumnNotFoundException::class);

        $this->builder
            ->table('animal')
            ->select(['column1', 'column2', 'column3'])
            ->where('parent_id', 2)
            ->count('column4');

    }

}