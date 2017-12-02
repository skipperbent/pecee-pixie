<?php

namespace Pecee\Pixie\QueryBuilder;

/**
 * Class Transaction
 *
 * @package Pecee\Pixie\QueryBuilder
 */
class Transaction extends QueryBuilderHandler
{
    /**
     * Check if we are in transaction
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo()->inTransaction();
    }

    /**
     * Begin transaction
     *
     * @param bool $inTransaction
     *
     * @return $this
     */
    public function begin(bool $inTransaction = false)
    {
        if (false === $inTransaction) {
            $this->pdo()->beginTransaction();
        }

        return $this;
    }

    /**
     * Commit transaction
     *
     * @param bool $inTransaction
     *
     * @return $this
     */
    public function commit(bool $inTransaction = false)
    {
        if (false === $inTransaction) {
            $this->pdo()->commit();
        }

        return $this;
    }

    /**
     * RollBack transaction
     *
     * @param bool $inTransaction
     *
     * @return $this
     */
    public function rollBack(bool $inTransaction = false)
    {
        if (false === $inTransaction) {
            $this->pdo()->rollBack();
        }

        return $this;
    }


}
