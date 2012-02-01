<?php
/**
 * Abstract table class
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
 * Abstract table class
 *
 * This is the base class for providing a unified interface to database tables.
 * It is not intended to be instantiated directly, but through one of the
 * {@link Nada_Dbms} methods.
 * @package NADA
 * @api
 */
abstract class Nada_Table
{
    /**
     * Database link
     * @var Nada_Dbms
     */
    protected $_dbms;

    /**
     * Table name
     * @var string
     */
    protected $_name;

    /**
     * This table's columns
     * @var array
     */
    protected $_columns;

    /**
     * Default set of columns to query from information_schema.columns
     *
     * Subclasses can extend this with DBMS-specific columns.
     * @var array
     */
    protected $_informationSchemaColumns = array(
        'column_name',
        'data_type',
        'character_maximum_length',
        'numeric_precision',
        'numeric_scale',
        'is_nullable',
        'column_default',
    );

    /**
     * Constructor
     * @param Nada_Dbms $dbms Database interface
     * @param string $name Table name
     * @throws RuntimeException if table does not exist
     */
    function __construct($dbms, $name)
    {
        $this->_dbms = $dbms;
        $this->_name = $name;

        $this->_columns = $this->_fetchColumns();
        if (empty($this->_columns)) {
            throw new RuntimeException('Table does not exist: ' . $name);
        }
    }

    /**
     * Factory method
     *
     * This should be preferred over direct instantiation.
     * @param Nada_Dbms $dbms Database interface
     * @param string $name Table name. An exception is thrown if the table does not exist.
     * @return NADA_Table DBMS-specific subclass
     */
    public static function factory($dbms, $name)
    {
        $class = 'Nada_Table_' . $dbms->getDbmsSuffix();
        return new $class($dbms, $name);
    }

    /**
     * Fetch column information from the database
     *
     * Invoked by the constructor, the default implementation queries
     * information_schema.columns for all columns belonging to this table. To
     * make this functional, subclasses must set {@link Nada_Dbms::_tableSchema}
     * and extend {@link _informationSchemaColumns} with essential DBMS-specific
     * columns.
     *
     * Overridden implementations must return columns in an information_schema
     * compatible structure.
     * @return array Query result
     */
    protected function _fetchColumns()
    {
        return $this->_dbms->query(
            'SELECT ' .
            implode(',', $this->_informationSchemaColumns) .
            ' FROM information_schema.columns WHERE table_schema=? AND table_name=? ORDER BY ordinal_position',
            array(
                $this->_dbms->getTableSchema(),
                $this->_name,
            )
        );
    }

    /**
     * Return all columns
     * @return array
     */
    public function getColumns()
    {
        return $this->_columns;
    }
}
