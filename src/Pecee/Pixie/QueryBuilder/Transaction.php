<?php
namespace Pecee\Pixie\QueryBuilder;

class Transaction extends QueryBuilderHandler
{

    /**
     * Commit the database changes
     * @throws TransactionHaltException
     */
    public function commit()
    {
        $this->pdo->commit();
        throw new TransactionHaltException();
    }

    /**
     * Rollback the database changes
     * @throws TransactionHaltException
     */
    public function rollback()
    {
        $this->pdo->rollBack();
        throw new TransactionHaltException();
    }
}
