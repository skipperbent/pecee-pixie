<?php

namespace Pecee\Pixie;

use PDOException;
use Pecee\Pixie\QueryBuilder\Adapters\Mysql;
use Pecee\Pixie\QueryBuilder\Adapters\Pgsql;
use Pecee\Pixie\QueryBuilder\Adapters\Sqlite;
use Pecee\Pixie\QueryBuilder\QueryObject;
use Pecee\Pixie\QueryException\DuplicateColumnException;
use Pecee\Pixie\QueryException\DuplicateEntryException;
use Pecee\Pixie\QueryException\DuplicateKeyException;
use Pecee\Pixie\QueryException\ForeignKeyException;
use Pecee\Pixie\QueryException\NotNullException;

/**
 * Class QueryException
 *
 * @package Pecee\Pixie
 */
class QueryException extends Exception
{
    /**
     * @param \PDOException                              $e
     * @param \Pecee\Pixie\QueryBuilder\QueryObject|null $query
     * @param string|null                                $adapterName
     *
     * @return Exception|DuplicateColumnException|DuplicateEntryException|DuplicateKeyException|ForeignKeyException|NotNullException|QueryException
     *
     * @see https://dev.mysql.com/doc/refman/5.6/en/error-messages-server.html
     * @see https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
     * @see https://sqlite.org/c3ref/c_abort.html
     */
    public static function create(PDOException $e, QueryObject $query = null, string $adapterName = null)
    {
        /**
         * @var string|null $errorSqlState
         * @var integer     $errorCode
         * @var string      $errorMsg
         */
        list($errorSqlState, $errorCode, $errorMsg) = $e->errorInfo;

        switch ($adapterName) {
            case Mysql::class: // https://dev.mysql.com/doc/refman/5.6/en/error-messages-server.html
                if ($errorSqlState === '23000') {
                    switch ($errorCode) {
                        case 1060: // Duplicate column name '%s'
                            return new DuplicateColumnException($errorMsg, 1, $e->getPrevious(), $query);
                        case 1061: // Message: Duplicate key name '%s'
                            return new DuplicateKeyException($errorMsg, 1, $e->getPrevious(), $query);
                        case 1062: // Message: Duplicate entry '%s' for key %d
                            return new DuplicateEntryException($errorMsg, 1, $e->getPrevious(), $query);
                        case 1451: // Message: Cannot delete or update a parent row: a foreign key constraint fails (%s)
                        case 1452: // Message: Cannot add or update a child row: a foreign key constraint fails (%s)
                            return new ForeignKeyException($errorMsg, 1, $e->getPrevious(), $query);
                        case 1048: // Column '%s' cannot be null
                            return new NotNullException($errorMsg, 1, $e->getPrevious(), $query);
                        default:
                            return new self(sprintf('Other PDO error: %s', $e->getMessage()), 1, $e);
                    }
                }
                break;
            case Pgsql::class: // https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
                switch ($errorSqlState) {
                    case '42701': // exclusion_violation
                        return new DuplicateColumnException($e->getMessage(), 1, $e->getPrevious(), $query);
                        break;
                    case '23000': // integrity_constraint_violation
                    case '23503': // foreign_key_violation
                        return new ForeignKeyException($e->getMessage(), 1, $e->getPrevious(), $query);
                        break;
                    case '23505': // unique_violation
                        return new DuplicateEntryException($e->getMessage(), 1, $e->getPrevious(), $query);
                        break;
                    case '23502': // not_null_violation
                        return new NotNullException($e->getMessage(), 1, $e->getPrevious(), $query);
                        break;
                    default:
                        return new self(sprintf('Other PDO error: %s', $e->getMessage()), 1, $e);
                }
                break;
            case Sqlite::class:  // https://sqlite.org/c3ref/c_abort.html
                if ($errorSqlState === '23000') {
                    switch ($errorCode) {
                        case \SQLITE_CONSTRAINT: // Abort due to constraint violation
                            return new NotNullException($errorMsg, 1, $e->getPrevious(), $query);
                        default:
                            return new self(sprintf('Other PDO error: %s', $e->getMessage()), 1, $e);
                    }
                }
                break;
            default:
                return new self($e->getMessage(), 0, $e->getPrevious(), $query);
        }

        return new self($e->getMessage(), 0, $e->getPrevious(), $query);
    }
}
