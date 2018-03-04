<?php

namespace Pecee\Pixie\QueryBuilder;

use Pecee\Pixie\Exception;
use Pecee\Pixie\Exceptions\TransactionHaltException;

/**
 * Class Transaction
 *
 * @package Pecee\Pixie\QueryBuilder
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
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws Exception
     * @throws TransactionHaltException
     */
    public function commit()
    {
        try {
            $this->pdo()->commit();
        } catch (\PDOException $e) {
            throw Exception::create($e, $this->adapter->getQueryAdapterClass(), $this);
        }

        throw new TransactionHaltException('Commit triggered transaction-halt.');
    }

    /**
     * Rollback transaction
     *
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws Exception
     * @throws TransactionHaltException
     */
    public function rollBack()
    {
        try {
            $this->pdo()->rollBack();
        } catch (\PDOException $e) {
            throw Exception::create($e, $this->adapter->getQueryAdapterClass(), $this);
        }

        throw new TransactionHaltException('Rollback triggered transaction-halt.');
    }

    /**
     * Execute statement
     *
     * @param string $sql
     * @param array $bindings
     *
     * @return array PDOStatement and execution time as float
     * @throws \Pecee\Pixie\Exceptions\NotNullException
     * @throws \Pecee\Pixie\Exceptions\ForeignKeyException
     * @throws \Pecee\Pixie\Exceptions\DuplicateKeyException
     * @throws \Pecee\Pixie\Exceptions\DuplicateEntryException
     * @throws \Pecee\Pixie\Exceptions\DuplicateColumnException
     * @throws Exception
     */
    public function statement(string $sql, array $bindings = []): array
    {
        $start = microtime(true);

        if ($this->transactionStatement === null && $this->pdo()->inTransaction() === true) {
            $this->transactionStatement = $this->pdo()->prepare($sql);
        }

        try {

            foreach ($bindings as $key => $value) {
                $this->transactionStatement->bindValue(
                    \is_int($key) ? $key + 1 : $key,
                    $value,
                    $this->parseParameterType($value)
                );
            }

            $this->transactionStatement->execute();
        } catch (\PDOException $e) {
            throw Exception::create($e, $this->adapter->getQueryAdapterClass(), $this);
        }

        return [$this->transactionStatement, microtime(true) - $start];
    }

}