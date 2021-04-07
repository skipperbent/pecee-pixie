<?php

namespace Pecee\Pixie\QueryBuilder;

use Pecee\Pixie\Exception;
use Pecee\Pixie\Exceptions\TransactionHaltException;

/**
 * Class Transaction
 */
class Transaction extends QueryBuilderHandler
{
    protected $transactionStatement;

    /**
     * @param \Closure $callback
     *
     * @return static
     */
    public function transaction(\Closure $callback): Transaction
    {
        $callback($this);

        return $this;
    }

    /**
     * Commit transaction
     *
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws TransactionHaltException
     */
    public function commit(): void
    {
        try {
            $this->pdo()->commit();
        } catch (\PDOException $e) {
            throw Exception::create($e, $this->getConnection()->getAdapter()->getQueryAdapterClass(), $this->getLastQuery());
        }

        throw new TransactionHaltException('Commit triggered transaction-halt.');
    }

    /**
     * Rollback transaction
     *
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws TransactionHaltException
     */
    public function rollBack(): void
    {
        try {
            $this->pdo()->rollBack();
        } catch (\PDOException $e) {
            throw Exception::create($e, $this->getConnection()->getAdapter()->getQueryAdapterClass(), $this->getLastQuery());
        }

        throw new TransactionHaltException('Rollback triggered transaction-halt.');
    }

    /**
     * Execute statement
     *
     * @param string $sql
     * @param array  $bindings
     *
     * @throws \Pecee\Pixie\Exceptions\TableNotFoundException
     * @throws \Pecee\Pixie\Exceptions\ConnectionException
     * @throws \Pecee\Pixie\Exceptions\ColumnNotFoundException
     * @throws \Pecee\Pixie\Exception
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws Exception
     *
     * @return array PDOStatement and execution time as float
     */
    public function statement(string $sql, array $bindings = []): array
    {
        if (null === $this->transactionStatement && true === $this->pdo()->inTransaction()) {
            $results                    = parent::statement($sql, $bindings);
            $this->transactionStatement = $results[0];

            return $results;
        }

        return parent::statement($sql, $bindings);
    }
}
