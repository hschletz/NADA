<?php
/**
 * Abstract link class
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
 * Abstract link class
 *
 * This is the base class for providing a unified interface to different
 * database access methods. It is not intended to be used directly in an
 * application, but only internally within {@link Nada_Dbms} methods.
 *
 * To add support for a particular database access method, derive a class from
 * Nada_Link and place it in the Link/ directory. Implement all methods that are
 * declared abstract in this class. Additionally, add detection for the link
 * type in {@link Nada::factory()}.
 * @package NADA
 */
abstract class Nada_Link
{
    /**
     * Database link
     * @var mixed
     */
    protected $_link;

    /**
     * Constructor
     * @param mixed DBAL-specific link object or ressource
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
     * base class name (like Nada_Dbms_Suffix) and to determine the file name of
     * the script that defines this class (like Dbms/Suffix.php). Because the
     * result is used to construct a path, implementations MUST take care that
     * it does not resolve into something evil.
     * @return string DBMS suffix
     * @throws UnexpectedValueException if no supported DBMS is detected
     */
    abstract public function getDbmsSuffix();

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
     * @param string $statement SQL statement
     * @return integer Number of affected rows
     * @throws Exception if execution fails
     */
    abstract public function exec($statement);

    /**
     * Get database server version
     * @return string Database server version
     */
    abstract public function getServerVersion();
}
