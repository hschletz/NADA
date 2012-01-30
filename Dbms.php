<?php
/**
 * Main application interface class
 *
 * $Id$
 *
 * Copyright (c) 2011,2012 Holger Schletz <holger.schletz@web.de>
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
/**
 * Main application interface class
 *
 * This is the base class for all objects through which applications will
 * interact. The public methods provide a DBMS-independent interface to
 * high-level database actions. Their inner workings and return values are
 * specific to the underlying DBMS.
 *
 * Objects should not be instantiated directly, but through
 * {@link Nada::factory()}.
 *
 * To add support for a particular DBMS, derive a class from Nada_Dbms and place
 * it in the Dbms/ directory. Override any method if the default implementation
 * from this class is not suitable. Additionally, add detection for the DBMS in
 * all {@link Nada_Link} derived classes for those database abstraction layers
 * that support this DBMS.
 * @package NADA
 * @api
 */
abstract class Nada_Dbms
{
    /**
     * Database link
     * @var Nada_Link
     */
    protected $_link;

    /**
     * Constructor
     * @param Nada_Link Database link
     */
    function __construct($link)
    {
        $this->_link = $link;
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
     * Execute a database statement that does not return a result set
     *
     * This method is intended to be used internally by NADA. Application code
     * should preferrably use methods from the underlying database abstraction
     * layer.
     *
     * SQL commands like UPDATE, INSERT, DELETE, SET etc. don't return a result
     * set. This method is intended for this type of commands. The return value
     * is typically the number of affected rows.
     * @param string $statement SQL statement with optional placeholders
     * @param mixed $params Single value or array of values to substitute for placeholders
     * @return integer Number of affected rows
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
     * Return an ISO style format string with a DBMS-recognized timestamp format
     *
     * This method returns a timestamp format string that, when passed to a
     * suitable formatting function, will yield a date string that will be
     * understood by the underlying DBMS. It returns only the format
     * specification, not a formatted timestamp.
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
        return 'yyyy-MM-ddTHH:mm:ssZZZZ';
    }

    /**
     * Return a PHP style format string with a DBMS-recognized timestamp format
     *
     * This method returns a timestamp format string that, when passed to a
     * suitable formatting function, will yield a date string that will be
     * understood by the underlying DBMS. It returns only the format
     * specification, not a formatted timestamp.
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
        return 'Y-m-d\TH:i:sP';
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
}
