<?php

namespace Pecee\Pixie\QueryBuilder;

/**
 * Class JoinBuilder
 *
 * @package Pecee\Pixie\QueryBuilder
 */
class JoinBuilder extends QueryBuilderHandler {
	/**
	 * @param string|Raw|\Closure $key
	 * @param string|Raw|\Closure $operator
	 * @param string|Raw|\Closure $value
	 *
	 * @return static
	 */
	public function on($key, $operator, $value) {
		return $this->joinHandler($key, $operator, $value);
	}

	/**
	 * @param string|Raw|\Closure $key
	 * @param string|Raw|\Closure $operator
	 * @param string|Raw|\Closure $value
	 *
	 * @return static
	 */
	public function orOn($key, $operator, $value) {
		return $this->joinHandler($key, $operator, $value, 'OR');
	}

	/**
	 * @param string|Raw|\Closure $key
	 * @param string|Raw|\Closure|null $operator
	 * @param string|Raw|\Closure|null $value
	 * @param string $joiner
	 *
	 * @return static
	 */
	protected function joinHandler($key, $operator = null, $value = null, $joiner = 'AND') {
		$key                            = $this->addTablePrefix($key);
		$value                          = $this->addTablePrefix($value);
		$this->statements['criteria'][] = compact('key', 'operator', 'value', 'joiner');

		return $this;
	}
}