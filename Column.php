<?php
/**
 * Abstract column class
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
 * Abstract column class
 *
 * This is the base class for providing a unified interface to database columns.
 * It is not intended to be instantiated directly, but through one of the
 * {@link Nada_Table} methods.
 * @package NADA
 * @api
 */
abstract class Nada_Column
{
    /**
     * Database link
     * @var Nada_Database
     */
    protected $_database;

    /**
     * Table that this column belongs to
     * @var Nada_Table
     */
    protected $_table;

    /**
     * Column name
     * @var string
     */
    protected $_name;

    /**
     * Column datatype
     * @var string
     */
    protected $_datatype;

    /**
     * Column length
     * @var string
     */
    protected $_length;

    /**
     * Column NOT NULL constraint
     * @var bool
     */
    protected $_notnull;

    /**
     * Column default
     * @var string
     */
    protected $_default;

    /**
     * TRUE if this is an autoincrement field
     * @var bool
     */
    protected $_autoIncrement;

    /**
     * Column comment
     * @var string
     */
    protected $_comment;

    /**
     * @internal
     * Internal factory method
     * @return NADA_Column DBMS-specific subclass
     */
    public static function factory($database)
    {
        $class = 'Nada_Column_' . $database->getDbmsSuffix();
        return new $class;
    }

    /**
     * @internal
     * Internal method to set up the object from within a Nada_Table object
     *
     * The column data can be of any type and is passed to the _parse*()
     * methods.
     * @param Nada_Table $table Table that this column belongs to
     * @param mixed $data Column data
     */
    public function constructFromTable($table, $data)
    {
        $this->_database = $table->getDatabase();
        $this->_table = $table;
        $this->_parseName($data);
        $this->_parseDatatype($data);
        $this->_parseNotnull($data);
        $this->_parseDefault($data);
        $this->_parseAutoIncrement($data);
        $this->_parseComment($data);
    }

    /**
     * @internal
     * Internal method to set up the object from Nada_Database::createColumn()
     * @param Nada_Database $database
     * @param string $name
     * @param string $type
     * @param mixed $length
     * @param bool $notnull
     * @param mixed $default
     * @param bool $autoIncrement
     * @param string $comment
     **/
    public function constructNew($database, $name, $type, $length, $notnull, $default, $autoIncrement, $comment)
    {
        $this->_database = $database;
        $this->_name = $name;
        $this->_datatype = $type;
        $this->_length = $length;
        $this->_notnull = $notnull;
        $this->_default = $default;
        $this->_autoIncrement = $autoIncrement;
        $this->_comment = $comment;
    }

    /**
     * @internal
     * Internal method to link this instance to a table object
     * @param Nada_Table $table Table object
     **/
    public function setTable($table)
    {
        $this->_table = $table;
    }

    /**
     * Extract name from column data
     *
     * The default implementation expects an array with information_schema
     * compatible keys.
     * @param mixed $data Column data
     */
    protected function _parseName($data)
    {
        $this->_name = $data['column_name'];
    }

    /**
     * Extract datatype and length from column data
     *
     * Since this part is DBMS-specific, no default implementation exists.
     * Implementations can expect an array with information_schema compatible
     * keys unless Nada_Table_NNN::_fetchColumns() generates something else.
     * @param mixed $data Column data
     * @throws UnexpectedValueException if datatype is not recognized.
     */
    abstract protected function _parseDatatype($data);

    /**
     * Extract NOT NULL constraint from column data
     *
     * The default implementation expects an array with information_schema
     * compatible keys.
     * @param mixed $data Column data
     */
    protected function _parseNotnull($data)
    {
        switch ($data['is_nullable']) {
            case 'YES':
                $this->_notnull = false;
                break;
            case 'NO':
                $this->_notnull = true;
                break;
            default:
                throw new UnexpectedValueException('Invalid yes/no type: ' . $data['is_nullable']);
        }
    }

    /**
     * Extract default value from column data
     *
     * The default implementation expects an array with information_schema
     * compatible keys.
     * @param mixed $data Column data
     */
    protected function _parseDefault($data)
    {
        $this->_default = $data['column_default'];
    }

    /**
     * Extract autoincrement property from column data
     *
     * Since this part is DBMS-specific, no default implementation exists.
     * Implementations can expect an array with information_schema compatible
     * keys unless Nada_Table_NNN::_fetchColumns() generates something else.
     * @param mixed $data Column data
     */
    abstract protected function _parseAutoIncrement($data);

    /**
     * Extract comment property from column data
     *
     * Since this part is DBMS-specific, no default implementation exists.
     * Implementations can expect an array with information_schema compatible
     * keys unless Nada_Table_NNN::_fetchColumns() generates something else.
     * @param mixed $data Column data
     */
    abstract protected function _parseComment($data);

    /**
     * Get column name
     * @return string Column name
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get table object which this instance is linked to
     * @return Nada_Table Parent table or NULL if this object was created via Nada_Database::createColumn()
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * Get column datatype
     *
     * The datatype is one of the constants defined in {@link Nada}. Depending
     * on the datatype, {@link getLength()} returns additional information.
     * @return string Abstract datatype constant
     */
    public function getDatatype()
    {
        return $this->_datatype;
    }

    /**
     * Get column length
     * @return string Column length (depending on datatype) or NULL.
     */
    public function getLength()
    {
        return $this->_length;
    }

    /**
     * Get NOT NULL constraint
     * @return bool TRUE if the column has a NOT NULL constraint.
     */
    public function getNotnull()
    {
        return $this->_notnull;
    }

    /**
     * Get default value
     * @return string Default value or NULL
     */
    public function getDefault()
    {
        return $this->_default;
    }

    /**
     * Get autoincrement property
     * @return bool TRUE if the column is an autoincrement field.
     */
    public function getAutoIncrement()
    {
        return $this->_autoIncrement;
    }

    /**
     * Get column comment
     * @return string Comment
     **/
    public function getComment()
    {
        return $this->_comment;
    }

    /**
     * Retrieve SQL fragment that describes the column properties
     *
     * The name is not part of the description. The return value is a DBMS-
     * specific pece of SQL like "VARCHAR(30) NOT NULL DEFAULT 'foo'".
     * @return string
     * @throws DomainException if Column properties are invalid
     **/
    abstract public function getDefinition();

    /**
     * Change the column's name
     *
     * If this instance is linked to a table, i.e. not created via Nada_Database::createColumn(),
     * the operation will be performed on the database.
     * @param string $name New name
     * @throws InvalidArgumentException if name is empty
     **/
    public function setName($name)
    {
        if (strlen($name) == 0) {
            throw new InvalidArgumentException('Column name must not be empty');
        }
        if ($this->_table) {
            // Call the table method before the property gets updated because the
            // old name is retrieved from $this
            $this->_table->renameColumn($this, $name);
        }
        $this->_name = $name;
    }

    /**
     * Set Column comment
     *
     * If this instance is linked to a table, i.e. not created via Nada_Database::createColumn(),
     * the operation will be performed on the database.
     * @param string $comment Comment (use NULL to remove comment)
     **/
    public function setComment($comment)
    {
        $this->_comment = $comment;
        if ($this->_table) {
            $this->_setComment($comment);
        }
    }

    /**
     * DBMS-specific implementation for setting a column comment
     * @param string $comment (use NULL to remove comment)
     **/
    abstract protected function _setComment();
}
