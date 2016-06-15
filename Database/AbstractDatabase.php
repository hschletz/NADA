<?php
/**
 * Database application interface class
 *
 * Copyright (C) 2011-2016 Holger Schletz <holger.schletz@web.de>
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package NADA
 */

namespace Nada\Database;

use Nada\Column\AbstractColumn as Column;

/**
 * Database application interface class
 *
 * This class is the starting point for applications. Its public methods provide
 * a DBMS-independent interface to high-level database actions. Their inner
 * workings and return values are specific to the underlying DBMS.
 *
 * Objects should not be instantiated directly, but through
 * \Nada\Factory::getDatabase().
 *
 * To add support for a particular DBMS, derive a class and override any method
 * if the default implementation is not suitable. Additionally, add detection
 * for the DBMS in all \Nada\Link\AbstractLink derived classes for those
 * database abstraction layers that support this DBMS.
 *
 * @package NADA
 * @api
 */
abstract class AbstractDatabase
{
    /**
     * Database link
     * @var \Nada\Link\AbstractLink
     */
    protected $_link;

    /**
     * DBMS-specific class suffix
     * @var string
     */
    protected $_dbmsSuffix;

    /**
     * Cache for current database name
     *
     * Managed by {@link getName()}, do not use directly.
     * @var string
     */
    protected $_name;

    /**
     * Value to search for in the table_schema column with information_schema queries
     *
     * This is DBMS-specific and must be set by a subclass that uses the default
     * information_schema implementation.
     * @var string
     */
    protected $_tableSchema;

    /**
     * Table cache
     *
     * {@link getTable()} stores its results here to avoid expensive requeries
     * of the same table.
     * @var array
     */
    private $_tables = array();

    /**
     * Flag that indicates if {@link getTables()} has already been invoked
     * @var bool
     */
    private $_allTablesFetched = false;

    /**
     * Tell prepareIdentifier() to always quote identifiers
     * @var bool
     */
    public $quoteAlways = false;

    /**
     * Tell prepareIdentifier() to quote identifiers with invalid characters
     * @var bool
     */
    public $quoteInvalidCharacters = false;

    /**
     * Keywords that should be quoted by prepareIdentifier()
     * @var array
     */
    public $quoteKeywords = array();

    /**
     * Datatypes that will be emulated if they are not natively supported
     *
     * Some datatypes are not available for every DBMS, like BOOL for MySQL.
     * They can be emulated, but the behavior may be slightly different. For
     * example, emulating BOOL with TINYINT will allow otherwise invalid input
     * (like 42) and not allow otherwise valid input (TRUE/FALSE). For that
     * reason, no emulation happens by default and must be explicitly enabled:
     *
     *     $database->emulatedDatatypes = array(\Nada\Column\AbstractColumn::TYPE_BOOL);
     *
     * @var array
     */
    public $emulatedDatatypes = array();

    /**
     * Constructor
     * @param \Nada\Link\AbstractLink Database link
     */
    function __construct($link)
    {
        $this->_link = $link;
        $this->_dbmsSuffix = $link->getDbmsSuffix();
    }

    /**
     * Get suffix for DBMS-specific classes
     *
     * This is useful for instantiation of DBMS-specific subclasses.
     * @return string Class suffix
     */
    public function getDbmsSuffix()
    {
        return $this->_dbmsSuffix;
    }

    /**
     * Return TRUE if underlying DBMS is MySQL
     * @return bool
     */
    public function isMysql()
    {
        return false; // MySQL-specific subclass overrides this
    }

    /**
     * Return TRUE if underlying DBMS is PostgreSQL
     * @return bool
     */
    public function isPgsql()
    {
        return false; // PostgreSQL-specific subclass overrides this
    }

    /**
     * Return TRUE if underlying DBMS is SQLite
     * @return bool
     */
    public function isSqlite()
    {
        return false; // SQLite-specific subclass overrides this
    }

    /**
     * Return the version string as reported by the database server
     */
    abstract public function getServerVersion();

    /**
     * Run database query and return complete result set
     *
     * This method is intended to be used internally by NADA. Application code
     * should preferrably use methods from the underlying database abstraction
     * layer.
     *
     * This method is intended for SELECT and similar commands that return a
     * result set. The result is returned as a 2-dimensional array. The outer
     * array is numeric and contains the rows. The rows are associative arrays
     * with lowercase column identifiers as keys.
     * @param string $statement SQL statement with optional placeholders
     * @param mixed $params Single value or array of values to substitute for placeholders
     * @return array Array of all rows
     * @internal
     */
    public function query($statement, $params=array())
    {
        if (!is_array($params)) {
            $params = array($params);
        }
        return $this->_link->query($statement, $params);
    }

    /**
     * Execute a database statement that does not return a result set
     *
     * This method is intended to be used internally by NADA. Application code
     * should preferrably use methods from the underlying database abstraction
     * layer.
     *
     * SQL commands like UPDATE, INSERT, DELETE, SET etc. don't return a result
     * set. This method is intended for this type of commands. The return value
     * is typically the number of affected rows.
     *
     * @param string $statement SQL statement with optional placeholders
     * @param mixed $params Single value or array of values to substitute for placeholders
     * @return integer Number of affected rows
     * @internal
     */
    public function exec($statement, $params=array())
    {
        if (!is_array($params)) {
            $params = array($params);
        }
        return $this->_link->exec($statement, $params);
    }

    /**
     * Return a case insensitive LIKE operator if available
     *
     * The behavior of the LIKE operator varies across DBMS. Sometimes it is
     * case sensitive, sometimes not. This method returns a case insensitive
     * LIKE operator, if available, otherwise just ' LIKE '. The returned string
     * can be inserted into an SQL statement as a drop-in replacement for LIKE.
     * It is encapsulated in spaces, so that surrounding spaces in the query
     * string are not required (but possible).
     *
     * **NOTE:** This method does not guarantee that the operation will be
     * case insensitive. The actual behavior may depend on several factors
     * outside this method's scope, like the particular table's collation. Some
     * DBMS may not even provide a case insensitive operator. This method's
     * result is the closest approximation to a really case insensitive
     * operation.
     *
     * Example:
     *
     *     // Works for all DBMS, no need to write DBMS-specific code
     *     $sql = 'SELECT foo FROM bar WHERE foo' . $nada->iLike() . '\'%foobar%\'';
     */
    public function iLike()
    {
        return ' LIKE ';
    }

    /**
     * Return a boolean literal
     *
     * Returns a string that can be inserted into an SQL statement to represent
     * a boolean literal. The default implementation provides "TRUE" and
     * "FALSE".
     *
     * @param bool $value
     * @return string
     */
    public function booleanLiteral($value)
    {
        return $value ? 'TRUE' : 'FALSE';
    }

    /**
     * Set timezone for the current connection
     * 
     * The timezone affects the presentation of timestamp values, like the
     * result of CURRENT_TIMESTAMP. Timestamp columns are typically not affected
     * and they hold the value that was originally assigned.
     *
     * The optional argument can set a particular timezone in a DBMS-specific
     * format. Leaving the argument at NULL is a portable way to set the
     * timezone to UTC. This is strongly recommended.
     *
     * @param string $timezone
     * @throws \LogicException if the requested operation is not implemented.
     */
    public function setTimezone($timezone = null)
    {
        throw new \LogicException('setTimezone() not implemented');
    }

    /**
     * Return an ISO style format string with a DBMS-recognized timestamp format
     *
     * This method returns a timestamp format string that, when passed to a
     * suitable formatting function, will yield a date string that will be
     * understood by the underlying DBMS. It returns only the format
     * specification, not a formatted timestamp.
     *
     * The format string does not contain a timezone specifier.
     *
     * The retuned string uses ISO 8601 format specifiers which are not
     * understood by PHP's builtin functions. Use {@link timestampFormatPhp()}
     * for those functions. This method is for code that understands the ISO
     * format, like Zend_Date.
     *
     * Example:
     *
     *     // Don't bother about timestamp format recognized by a particular DBMS
     *     $params = array(
     *         $zend_date->get($nada->timestampFormatIso())
     *     );
     *     $pdoStatement->execute($params);
     * @return string ISO 8601 compliant format string
     */
    public function timestampFormatIso()
    {
        // Default implementation yields full ISO 8601 timestamp, like
        // 2011-10-17T17:19:38+02:00. Override if necessary.
        return 'yyyy-MM-dd HH:mm:ss';
    }

    /**
     * Return a PHP style format string with a DBMS-recognized timestamp format
     *
     * This method returns a timestamp format string that, when passed to a
     * suitable formatting function, will yield a date string that will be
     * understood by the underlying DBMS. It returns only the format
     * specification, not a formatted timestamp.
     *
     * The format string does not contain a timezone specifier.
     *
     * The retuned string uses PHP-style specifiers which are understood by
     * PHP's builtin functions. Use {@link timestampFormatIso()} for functions
     * that expect ISO 8601 format specification.
     *
     * Example:
     *
     *     // Don't bother about timestamp format recognized by a particular DBMS
     *     $params = array(
     *         date($nada->timestampFormatIso())
     *     );
     *     $pdoStatement->execute($params);
     * @return string PHP style format string
     */
    public function timestampFormatPhp()
    {
        // Default implementation yields full ISO 8601 timestamp, like
        // 2011-10-17T17:19:38+02:00. Override if necessary.
        return 'Y-m-d H:i:s';
    }

    /**
     * Convert timestamp columns
     *
     * Converts all timestamp columns in a way that allows for maximum
     * portability. In particular, portable timestamps don't support fractional
     * seconds or time zones. Some implementations like MySQL's TIMESTAMP
     * datatype (as opposed to the DATETIME type) expose nonstandard behavior
     * which is removed as well.
     *
     * @return integer number of converted columns
     */
    public function convertTimestampColumns()
    {
        // DBMS-specific actions provided by subclass
        return 0;
    }

    /**
     * Prepare an identifier (table/column name etc.) for insertion into an SQL statement
     *
     * Inserting identifiers from external sources into an SQL statement is
     * tricky and error-prone. They can contain invalid characters, match an SQL
     * keyword on some DBMS, or be abused for SQL injection. This problem is
     * typically addressed by quoting and escaping identifiers from untrusted
     * sources. This raises a new problem: According to SQL standards, quoted
     * identifiers may become case sensitive while unquoted identifiers are case
     * insensitive. Under certain circumstances, the referred object may become
     * impossible to address safely across all DBMS.
     *
     * This method is a more flexible alternative to unconditional quoting. By
     * default, no quoting and escaping is applied for maximum compatibility.
     * Identifiers that would require quoting raise an exception.
     *
     * For maximum portability, this method is rather strict about valid
     * identifiers: They must contain only letters, digits and underscores and
     * must not begin with a digit. To allow identifiers that do not meet these
     * criteria, set the public {@link quoteInvalidCharacters} property to TRUE.
     * This will quote and escape identifiers that contain invalid characters.
     * This is a bad idea and the object should rather be renamed if possible.
     * SQL injection is prevented either way.
     *
     * The public {@link quoteKeywords} property can be set to an array of
     * keywords that would raise an error if inserted unquoted. If $identifier
     * matches one of those keywords, it will be quoted. However, naming a
     * database object with a reserved keyword is a bad idea. Consider renaming
     * it if possible. Concealing the flaw by applying quotes is only a solution
     * if the object cannot be renamed. For this reason, {@link quoteKeywords}
     * is empty by default.
     *
     * Finally, the public {@link quoteAlways} property can be set to TRUE to
     * quote all identifiers unconditionally. This should only be done if an
     * identifier is only known at runtime and nothing is known about it
     * otherwise.
     *
     * Even with proper quoting, bad things may happen if untrusted input is
     * used as an identifier. Don't rely on this method alone - always sanitize
     * input before using it as an identifier.
     * @param string $identifier Identifier to check
     * @return string Identifier, quoted and escaped if necessary
     * @throws \RuntimeException if identifier is invalid and not quoted.
     */
    public function prepareIdentifier($identifier)
    {
        if ($this->quoteAlways or in_array($identifier, $this->quoteKeywords)) {
            return $this->quoteIdentifier($identifier);
        }

        if (!preg_match('/^[_[:alpha:]][_[:alnum:]]*$/', $identifier)) {
            if ($this->quoteInvalidCharacters) {
                return $this->quoteIdentifier($identifier);
            } else {
                throw new \RuntimeException('Invalid characters in identifier: ' . $identifier);
            }
        }

        return $identifier;
    }

    /**
     * Quote and escape an identifier (table/column name etc.) for insertion into an SQL statement
     *
     * This method unconditionally quotes an identifier. This is discouraged in
     * favour of {@link prepareIdentifier()}.
     * @param string $identifier Identifier to quote and escape
     * @return string Quoted and escaped identifier
     */
    public function quoteIdentifier($identifier)
    {
        $quotedIdentifier = $this->_link->quoteIdentifier($identifier);
        if ($quotedIdentifier === null) {
            $quotedIdentifier = $this->_quoteIdentifier($identifier);
        }
        return $quotedIdentifier;
    }

    /**
     * DBMS-specific implementation for quoting and escaping identifiers
     *
     * The default implementation uses ANSI-style quoting and escaping. Override
     * if necessary.
     * @param string $identifier Identifier to quote and escape
     * @return string Quoted and escaped identifier
     */
    protected function _quoteIdentifier($identifier)
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Prepare a literal value for insertion into an SQL statement
     * @param mixed $value Value to prepare
     * @param string $datatype The value's datatype
     * @return string Value, processed, quoted and escaped if necessary
     */
     public function prepareValue($value, $datatype)
     {
        if ($value === null) {
            return 'NULL';
        }
        switch ($datatype) {
            case Column::TYPE_INTEGER:
                // Filter explicitly because some DBAL silently convert/truncate to integer
                $filtered = filter_var($value, FILTER_VALIDATE_INT);
                if ($filtered === false) {
                    throw new \InvalidArgumentException('Not an integer: '. $value);
                }
                return $filtered; // No quotes necessary
            case Column::TYPE_FLOAT:
            case Column::TYPE_DECIMAL:
                // Filter explicitly because some DBAL silently convert/truncate to float
                $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
                if ($filtered === false) {
                    throw new \InvalidArgumentException('Not a number: '. $value);
                }
                return $filtered; // No quotes necessary
            case Column::TYPE_BOOL:
                if (is_bool($value)) { // filter_var() does not work with real booleans
                    $filtered = $value;
                } else {
                    $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
                if ($filtered === null) {
                    throw new \InvalidArgumentException('Not a boolean: ' . $value);
                }
                return (integer)$filtered; // Convert to 0/1 for compatibility with emulated booleans
            case Column::TYPE_BLOB:
                // Handled differently across DBMS and abstraction layers - refuse by default.
                throw new \InvalidArgumentException('Cannot prepare BLOB values');
            default:
                return $this->_link->quoteValue($value, $datatype);
        }
     }

    /**
     * Set strict and more standards compliant behavior on database connection
     *
     * When developing with a particular DBMS, it can often happen that a
     * specific SQL syntax is used that will not work with other DBMS. The
     * runtime behavior can also be different in certain situations. This may
     * result in code that works fine on the development platform, but causes
     * problems with other DBMS. Even if the code is tested across different
     * DBMS, some problems may still remain unnoticed because they only show
     * up under very specific conditions.
     *
     * Many DBMS can be configured to be more strict and standards compliant so
     * that some compatibility problems can be detected immediately. This method
     * turns on some compatibility options for the current connection (other
     * connections will not be affected).
     *
     * **WARNING:** Turning on these options for an existing application may
     * reveal previously unnoticed problems and stop the application from
     * working until these issues are resolved. This method is intended for
     * development purposes only. It is not advisable for a production
     * environment.
     */
    public function setStrictMode()
    {
        // This is essentially DBMS specific.
        // Any actions are implemented in a subclass.
    }

    /**
     * Return name of the database that the current connection points to
     * @return string Database name
     */
    public function getName()
    {
        if (!$this->_name) {
            $result = $this->query(
                'select catalog_name from information_schema.information_schema_catalog_name'
            );
            $this->_name = $result[0]['catalog_name'];
        }
        return $this->_name;
    }

    /**
     * Get access to a specific table
     *
     * The returned object provides access to the given table and its associated
     * database objects (columns etc.). The result is cached internally, so that
     * subsequent calls won't hurt performance.
     * @param string $name Table name (lowercase). An exception gets thrown if the name does not exist.
     * @return \Nada\Table\AbstractTable Table object
     * @throws \DomainException if $name is not lowercase
     */
    public function getTable($name)
    {
        if ($name != strtolower($name)) {
            throw new \DomainException('Table name must be lowercase: ' . $name);
        }

        if (!isset($this->_tables[$name])) {
            $class = 'Nada\Table\\' . $this->getDbmsSuffix();
            $this->_tables[$name] = new $class($this, $name);
        }
        return $this->_tables[$name];
    }

    /**
     * Return all tables
     *
     * The result is cached internally, so that subsequent calls won't hurt performance.
     *
     * @return array Array with table names as keys and \Nada\Table\AbstractTable objects as values
     */
    public function getTables()
    {
        if (!$this->_allTablesFetched) { // Fetch only once
            $tables = $this->getTableNames();
            // Fetch missing tables
            foreach ($tables as $table) {
                $this->getTable($table); // Discard result, still available in cache
            }
            // Sort cache
            ksort($this->_tables);
            // Set flag for subsequent invocations
            $this->_allTablesFetched = true;
        }
        return $this->_tables;
    }

    /**
     * Return list of all table names
     * 
     * The default implementation queries information_schema. This can be
     * overridden where information_schema is not available.
     *
     * @return array
     */
    public function getTableNames()
    {
        $names = $this->query(
            'SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_type=?',
            array(
                $this->getTableSchema(),
                'BASE TABLE'
            )
        );
        // Flatten array
        foreach ($names as &$name) {
            $name = $name['table_name'];
        }
        return $names;
    }

    /**
     * Return list of all view names
     *
     * The default implementation queries information_schema. This can be
     * overridden where information_schema is not available.
     *
     * @return array
     */
    public function getViewNames()
    {
        $names = $this->query(
            'SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_type=?',
            array(
                $this->getTableSchema(),
                'VIEW'
            )
        );
        // Flatten array
        foreach ($names as &$name) {
            $name = $name['table_name'];
        }
        return $names;
    }

    /**
     * Clear cache
     *
     * NADA caches the database structure internally. The structure manipulation
     * methods maintain the cache automatically. If the structure has been
     * altered without one of NADA's methods, the cache is outdated and needs to
     * be flushed with this method.
     *
     * @param string $table Optional: Flush only the given table instead of the entire cache
     **/
    public function clearCache($table=null)
    {
        if ($table) {
            unset($this->_tables[$table]);
        } else {
            $this->_tables = array();
        }
        $this->_allTablesFetched = false;
    }

    /**
     * Get database-specific table_schema value for information_schema queries
     *
     * The returned string should be used as a filter on the table_schema column
     * for information_schema queries to limit results to the current database.
     * @return string
     */
    public function getTableSchema()
    {
        return $this->_tableSchema;
    }

    /**
     * Get DBMS-specific datatype for abstract NADA datatype
     * @param string $type One of the \Nada\Column\AbstractColumn::TYPE_* constants
     * @param mixed $length Optional length modifier (default provided for some datatypes)
     * @param bool $cast The result is used for a typecast operation. Some DBMS require different specifiers for casts.
     * @return string SQL fragment representing the datatype
     * @throws \DomainException if the datatype is not supported for the current DBMS
     * @throws \InvalidArgumentException if $length is invalid
     **/
    public function getNativeDatatype($type, $length=null, $cast=false)
    {
        switch ($type) {
            case Column::TYPE_INTEGER:
                if ($length === null) {
                    $length = Column::DEFAULT_LENGTH_INTEGER;
                }
                switch ($length) {
                    case 16:
                        return 'SMALLINT';
                    case 32:
                        return 'INTEGER';
                    case 64:
                        return 'BIGINT';
                    default:
                        throw new \DomainException("Invalid length for type $type: $length");
                }
            case Column::TYPE_VARCHAR:
                if (!ctype_digit((string)$length) or $length < 1) {
                    throw new \InvalidArgumentException('Invalid length: ' . $length);
                }
                return "VARCHAR($length)";
            case Column::TYPE_TIMESTAMP:
                return 'TIMESTAMP';
            case Column::TYPE_DATE:
                return 'DATE';
            case Column::TYPE_BOOL:
                return 'BOOLEAN';
            case Column::TYPE_BLOB:
                return 'BLOB';
            case Column::TYPE_DECIMAL:
                if (!preg_match('/^([0-9]+),([0-9]+)$/', $length, $components)) {
                    throw new \InvalidArgumentException('Invalid length: ' . $length);
                }
                $precision = (integer)$components[1];
                $scale = (integer)$components[2];
                if ($precision < $scale) {
                    throw new \InvalidArgumentException('Invalid length: ' . $length);
                }
                return "NUMERIC($precision,$scale)";
            case Column::TYPE_FLOAT:
                if ($length === null) {
                    $length = Column::DEFAULT_LENGTH_FLOAT;
                } elseif (!ctype_digit((string)$length) or $length < 1) {
                    throw new \InvalidArgumentException('Invalid length: ' . $length);
                }
                return "FLOAT($length)";
            default:
                throw new \DomainException('Unsupported datatype: ' . $type);
        }
    }

    /**
     * Create empty column object
     *
     * @return \Nada\Column\AbstractColumn DBMS-specific subclass
     */
    public function createColumnObject()
    {
        $class = 'Nada\Column\\' . $this->getDbmsSuffix();
        return new $class;
    }

    /**
     * Construct a new column
     *
     * Input is not validated at this point, i.e. this method always succeeds.
     * Invalid input is detected once the column data is actually used.
     *
     * The returned object is not linked to a table. It should only be used for
     * adding it to a table. It can be discarded afterwards.
     * @param string $name Column name
     * @param string $type Datatype
     * @param mixed $length Optional length modifier
     * @param bool $notnull NOT NULL constraint (default FALSE)
     * @param mixed $default Default value (default NULL)
     * @param bool $autoIncrement Auto increment property (default FALSE)
     * @param string $comment Column comment (default: NULL)
     * @return \Nada\Column\AbstractColumn Temporary column object
     **/
    public function createColumn(
        $name,
        $type,
        $length=null,
        $notnull=false,
        $default=null,
        $autoIncrement=false,
        $comment=null
    )
    {
        $column = $this->createColumnObject();
        $column->constructNew($this, $name, $type, $length, $notnull, $default, $autoIncrement, $comment);
        return $column;
    }

    /**
     * Construct a new column with data from an array
     *
     * This is a wrapper around createColumn() which gets column data from an
     * associative array.
     *
     * The "name", "type" and "length" elements must always be present. The
     * "notnull" and "autoincrement" elements default to FALSE if not given. The
     * "default" and "comment" elements default to NULL.
     *
     * @param array $data column data: name, type, length, notnull, default, autoincrement, comment
     * @return \Nada\Column\AbstractColumn Temporary column object
     */
    public function createColumnFromArray(array $data)
    {
        return $this->createColumn(
            $data['name'],
            $data['type'],
            $data['length'],
            !empty($data['notnull']),
            @$data['default'],
            !empty($data['autoincrement']),
            @$data['comment']
        );
    }

    /**
     * Create a table
     *
     * Some constraints are checked before the creation is attempted:
     *
     * - There can be at most 1 autoincrement column.
     * - If there is an autoincrement column, it must be the primary key.
     * - Otherwise, $primaryKey must not be empty.
     * - Table names must not begin with sqlite_. For maximum compatibility,
     *   this constraint is enforced for all DBMS, not only for SQLite.
     *
     * The $primaryKey value can be omitted for tables with an autoincrement
     * column because the autoincrement column is automatically made the primary
     * key.
     *
     * @param string $name Table name
     * @param array $columns Array of column objects, created via createColumn(), or associative arrays
     * @param string|array $primaryKey Primary key (column name or aray of column names)
     * @return \Nada\Table\AbstractTable A representation of the created table
     * @throws \InvalidArgumentException if $columns is empty or constraints are violated
     * @throws \RuntimeException if the table already exists
     */
    public function createTable($name, array $columns, $primaryKey=null)
    {
        $name = strtolower($name);
        if (strpos($name, 'sqlite_') === 0) {
            throw new \InvalidArgumentException('Created table name must not begin with sqlite_');
        }
        if (empty($columns)) {
            throw new \InvalidArgumentException('No columns specified for new table');
        }
        if ($primaryKey !== null and !is_array($primaryKey)) {
            $primaryKey = array($primaryKey);
        }
        $numAutoIncrement = 0;
        $autoPk = null;
        foreach ($columns as &$column) {
            if (is_array($column)) {
                $column = $this->createColumnFromArray($column);
            }
            if ($column->getAutoIncrement()) {
                $numAutoIncrement++;
                $autoPk = $column->getName();
            }
        }
        unset($column);
        if ($numAutoIncrement > 1) {
            throw new \InvalidArgumentException(
                'More than 1 autoincrement field specified, given: ' . $numAutoIncrement
            );
        }
        if ($autoPk) {
            if ($primaryKey === null) {
                $primaryKey = array($autoPk);
            } elseif (count($primaryKey) != 1 or $autoPk != $primaryKey[0]) {
                throw new \InvalidArgumentException('Invalid primary key: ' . implode(',', $primaryKey));
            }
        }
        if (!$primaryKey) {
            throw new \InvalidArgumentException('Missing primary key for table ' . $name);
        }
        if (in_array($name, $this->getTableNames())) {
            throw new \RuntimeException('Table already exists: ' . $name);
        }

        $colspecs = array();
        foreach ($columns as $column) {
            $colspecs[] = $this->prepareIdentifier($column->getName()) . ' ' . $column->getDefinition();
        }

        $sql = 'CREATE TABLE ' . $this->prepareIdentifier($name) . " (\n";
        $sql .= implode(",\n", $colspecs);
        $sql .= $this->_getTablePkDeclaration($primaryKey, (bool) $numAutoIncrement);
        $sql .= "\n)";

        $this->exec($sql);
        return $this->getTable($name);
    }

    /**
     * Get the declaration for a table's primary key
     *
     * This is called by createTable() to get the PRIMARY KEY declaration to be
     * inserted into a CREATE TABLE statement. The default implementation 
     * unconditionally returns ", PRIMARY KEY ($column[,$column...])". Other
     * implementations may override this, depending on $autoIncrement.
     *
     * @param array $primaryKey Array of column names for PK
     * @param bool $autoIncrement Whether the table has an autoincrement column
     * @return string
     */
    protected function _getTablePkDeclaration(array $primaryKey, $autoIncrement)
    {
        foreach ($primaryKey as &$column) {
            $column = $this->prepareIdentifier($column);
        }
        return ",\nPRIMARY KEY (" . implode(', ', $primaryKey) .  ')';
    }

    /**
     * Drop a table
     *
     * @param string $name Table name
     */
    public function dropTable($name)
    {
        $this->exec('DROP TABLE ' . $this->prepareIdentifier($name));
        $this->clearCache($name);
    }

    /**
     * Create a view
     *
     * For portability, views should be used read-only.
     *
     * @param string $name View name
     * @param string $query SQL query defining the view
     */
    public function createView($name, $query)
    {
        $this->exec('CREATE VIEW ' . $this->prepareIdentifier($name) . ' AS ' . $query);
    }
}
