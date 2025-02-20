<?php

namespace Nada\Link;

/**
 * Abstract link class
 *
 * This is the base class for providing a unified interface to different
 * database access methods. It is not intended to be used directly in an
 * application, but only internally within NADA methods.
 *
 * To add support for a particular database access method, derive a class and
 * implement all methods that are declared abstract in this class. Additionally,
 * add detection for the link type in \Nada\Factory::getDatabase().
 */
abstract class AbstractLink
{
    /**
     * Database link
     * @var mixed
     */
    protected $_link;

    /**
     * Constructor
     * @param mixed $link DBAL-specific link object or ressource
     */
    function __construct($link)
    {
        $this->_link = $link;
    }

    /**
     * Detect DBMS type and return suffix for its class
     *
     * Implementations should detect all DBMS types supported by both NADA and
     * the database abstraction layer. The returned suffix is appended to the
     * base class namespace. Because the result is used to construct a class
     * name, implementations MUST take care that it does not resolve into
     * something evil.
     *
     * @return string DBMS suffix
     * @throws \UnexpectedValueException if no supported DBMS is detected
     */
    abstract public function getDbmsSuffix();

    /**
     * Run database query and return complete result set
     *
     * This method is intended for SELECT and similar commands that return a
     * result set. The result is returned as a 2-dimensional array. The outer
     * array is numeric and contains the rows. The rows are associative arrays
     * with lowercase column identifiers as keys.
     *
     * Implementations must ensure that an exception gets thrown upon errors,
     * either by the implementation itself or by the underlying database access
     * method.
     * @param string $statement SQL statement with optional placeholders
     * @param array $params Values to substitute for placeholders
     * @return array Array of all rows
     * @throws \Exception if execution fails
     */
    abstract public function query($statement, $params);

    /**
     * Execute a database statement that does not return a result set
     *
     * SQL commands like UPDATE, INSERT, DELETE, SET etc. don't return a result
     * set. This method is intended for this type of commands. The return value
     * is typically the number of affected rows.
     *
     * Implementations must ensure that an exception gets thrown upon errors,
     * either by the implementation itself or by the underlying database access
     * method.
     * @param string $statement SQL statement with optional placeholders
     * @param array $params Values to substitute for placeholders
     * @return integer Number of affected rows
     * @throws \Exception if execution fails
     */
    abstract public function exec($statement, $params);

    /**
     * Quote a literal value
     *
     * This method should only be called with values that require quotes. It may
     * not work properly with numbers, NULL and similar.
     * @param string $value Value to quote
     * @param string $datatype Value's datatype
     * @return string Quoted and escaped value
     **/
    abstract public function quoteValue($value, $datatype);

    /**
     * Quote an identifier
     *
     * The default implementation returns NULL in which case the calling code
     * must use its own implementation for quoting and escaping identifiers.
     * Derived classes should override it with the appropriate method provided
     * by the database abstraction layer, if available. This may be more
     * sophisticated regarding charset handling etc.
     * @param string $identifier Identifier to quote
     **/
    public function quoteIdentifier($identifier): ?string
    {
        return null;
    }

    /**
     * Report running transaction.
     */
    abstract public function inTransaction(): bool;
}
