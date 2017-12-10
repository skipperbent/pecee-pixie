<?php

namespace Pecee\Pixie\QueryBuilder;

/**
 * Class Transaction
 *
 * @package Pecee\Pixie\QueryBuilder
 */
class Transaction extends QueryBuilderHandler {

	protected $transactionStatement;

	/**
	 * @param \Closure $callback
	 *
	 * @return static
	 */
	public function transaction(\Closure $callback) {
		$callback($this);

		return $this;
	}

	/**
	 * Commit transaction
	 *
	 * @throws \PDOException|TransactionHaltException
	 */
	public function commit() {
		$this->pdo->commit();
		throw new TransactionHaltException('Commit triggered transaction-halt.');
	}

	/**
	 * RollBack transaction
	 *
	 * @throws \PDOException|TransactionHaltException
	 */
	public function rollBack() {
		$this->pdo->rollBack();
		throw new TransactionHaltException('Rollback triggered transaction-halt.');
	}

	/**
	 * Execute statement
	 *
	 * @param string $sql
	 * @param array $bindings
	 *
	 * @return array PDOStatement and execution time as float
	 */
	public function statement($sql, array $bindings = []) {
		$start = microtime(true);

		if ($this->transactionStatement === null && $this->pdo->inTransaction() === true) {
			$this->transactionStatement = $this->pdo->prepare($sql);
		}

		$this->transactionStatement->execute($bindings);

		return [$this->transactionStatement, microtime(true) - $start];
	}

}
