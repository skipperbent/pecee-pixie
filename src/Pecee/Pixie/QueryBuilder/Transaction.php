<?php

namespace Pecee\Pixie\QueryBuilder;

use Pecee\Pixie\Exception;

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
    public function transaction(\Closure $callback)
    {
        $callback($this);

        return $this;
    }

    /**
     * Commit transaction
     *
     * @throws Exception|TransactionHaltException
     */
    public function commit()
    {
        try {
            $this->pdo->commit();
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious(), $this->getConnection()->getLastQuery());
        }

        throw new TransactionHaltException('Commit triggered transaction-halt.');
    }

    /**
     * RollBack transaction
     *
     * @throws Exception|TransactionHaltException
     */
    public function rollBack()
    {
        try {
            $this->pdo->rollBack();
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious(), $this->getConnection()->getLastQuery());
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
     * @throws Exception
     */
    public function statement($sql, array $bindings = [])
    {
        $start = microtime(true);

        if ($this->transactionStatement === null && $this->pdo->inTransaction() === true) {
            $this->transactionStatement = $this->pdo->prepare($sql);
        }

        try {

            $this->transactionStatement->execute($bindings);
        } catch(\PDOException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious(), $this->getConnection()->getLastQuery());
        }

        return [$this->transactionStatement, microtime(true) - $start];
    }

}
