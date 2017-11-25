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
     * Commit the database changes
     * @throws TransactionHaltException
     */
    public function commit()
    {
        $this->pdo->commit();
        throw new TransactionHaltException('Commit');
    }

    /**
     * Rollback the database changes
     * @throws TransactionHaltException
     */
    public function rollback()
    {
        $this->pdo->rollBack();
        throw new TransactionHaltException('Rollback');
    }
}
