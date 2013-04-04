<?php
/**
 * Abstract table class
 *
 * Copyright (C) 2011-2013 Holger Schletz <holger.schletz@web.de>
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
 * {@link Nada_Database} methods.
 * @package NADA
 * @api
 */
abstract class Nada_Table
{
    /**
     * Database link
     * @var Nada_Database
     */
    protected $_database;

    /**
     * Table name
     * @var string
     */
    protected $_name;

    /**
     * This table's columns
     *
     * This is an associative array populated by {@link _fetchColumns()}.
     * The column name is the key, and the value is a Nada_Column derived class.
     * @var array
     */
    protected $_columns;

    /**
     * Default set of columns to query from information_schema.columns
     *
     * Subclasses can extend this with DBMS-specific columns.
     * Values are inserted directly into a FROM clause and therefore interpreted
     * as an arbitrary column expression.
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
     * @param Nada_Database $database Database interface
     * @param string $name Table name
     * @throws RuntimeException if table does not exist
     */
    function __construct($database, $name)
    {
        $this->_database = $database;
        $this->_name = $name;

        $this->_fetchColumns();
        if (empty($this->_columns)) {
            throw new RuntimeException('Table does not exist: ' . $name);
        }
    }

    /**
     * Factory method
     *
     * This should be preferred over direct instantiation.
     * @param Nada_Database $database Database interface
     * @param string $name Table name. An exception is thrown if the table does not exist.
     * @return NADA_Table DBMS-specific subclass
     */
    public static function factory($database, $name)
    {
        $class = 'Nada_Table_' . $database->getDbmsSuffix();
        return new $class($database, $name);
    }

    /**
     * Return table name
     * @return string table name
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Return database interface
     * @return Nada_Database Database interface
     */
    public function getDatabase()
    {
        return $this->_database;
    }

    /**
     * Fetch column information from the database
     *
     * Invoked by the constructor, the default implementation queries
     * information_schema.columns for all columns belonging to this table. To
     * make this functional, subclasses must set {@link Nada_Database::$_tableSchema}
     * and, if necessary, extend {@link $_informationSchemaColumns} with
     * DBMS-specific columns.
     */
    protected function _fetchColumns()
    {
        $columns = $this->_database->query(
            'SELECT ' .
            implode(',', $this->_informationSchemaColumns) .
            ' FROM information_schema.columns WHERE table_schema=? AND LOWER(table_name)=? ORDER BY ordinal_position',
            array(
                $this->_database->getTableSchema(),
                $this->_name,
            )
        );
        foreach ($columns as $column) {
            $column['column_name'] = strtolower($column['column_name']);
            $object = Nada_Column::factory($this->_database);
            $object->constructFromTable($this, $column);
            $this->_columns[$column['column_name']] = $object;
        }
    }

    /**
     * Return a single column
     * @param $name Column name
     * @return Nada_Column Column interface
     */
    public function getColumn($name)
    {
        $this->requireColumn($name);
        return $this->_columns[$name];
    }

    /**
     * Return all columns
     * @return array Array of Nada_Column objects with column names as keys
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    /**
     * Force presence of column
     * @param string $name Column name to check for, must be lowercase
     * @throws RuntimeException if column does not exist
     * @throws DomainException if $name is not lowercase
     */
    public function requireColumn($name)
    {
        if ($name != strtolower($name)) {
            throw new DomainException('Column name must be lowercase: ' . $name);
        }

        if (!isset($this->_columns[$name])) {
            throw new RuntimeException('Undefined column: ' . $this->_name . '.' . $name);
        }
    }

    /**
     * Force Absence of column
     * @param string $name Column name to check for, must be lowercase
     * @throws RuntimeException if column exists
     * @throws DomainException if $name is not lowercase
     */
    public function forbidColumn($name)
    {
        if ($name != strtolower($name)) {
            throw new DomainException('Column name must be lowercase: ' . $name);
        }

        if (isset($this->_columns[$name])) {
            throw new RuntimeException('Already defined column: ' . $this->_name . '.' . $name);
        }
    }

    /**
     * Compose and execute an ALTER TABLE statement
     *
     * This method simply appends the given operation to an "ALTER TABLE name "
     * statement and executes it via exec(). Since the operation syntax is often
     * DBMS-specific, this method is mostly useful for other NADA methods.
     * @param string $operation SQL fragment
     * @return mixed Return value of exec()
     **/
    public function alter($operation)
    {
        return $this->_database->exec(
            'ALTER TABLE ' .
            $this->_database->prepareIdentifier($this->_name) .
            ' ' .
            $operation
        );
    }

    /**
     * Add a column using parameters
     * @param string $name Column name
     * @param string $type Datatype, one of the Nada::DATATYPE_* constants
     * @param mixed $length Optional length specification
     * @param bool $notnull NOT NULL constraint (default: FALSE)
     * @param mixed $default Default value (DEFAULT: NULL)
     * @param bool $autoIncrement Auto increment property (default: FALSE)
     * @param string $comment Column comment (default: NULL)
     * @return Nada_Column object describing the generated column
     **/
    public final function addColumn(
        $name,
        $type,
        $length=null,
        $notnull=false,
        $default=null,
        $autoIncrement=false,
        $comment = null
    )
    {
        return $this->addColumnObject(
            $this->_database->createColumn(
                $name,
                $type,
                $length,
                $notnull,
                $default,
                $autoIncrement,
                $comment
            )
        );
    }


    /**
     * Add a column using a Nada_Column object
     * @param Nada_Column Column object
     * @return Nada_Column object describing the generated column
     **/
    public function addColumnObject($column)
    {
        $name = $column->getName();

        $this->forbidColumn($name);

        $sql = 'ADD COLUMN ';
        $sql .= $this->_database->prepareIdentifier($name);
        $sql .= ' ';
        $sql .= $column->getDefinition();

        $this->alter($sql);

        // Update column cache
        $this->_columns[$name] = $column;
        $this->_columns[$name]->setTable($this);
        return $this->_columns[$name];
    }

    /**
     * Drop a column
     * @param string Column name
     */
    public function dropColumn($name)
    {
        $this->requireColumn($name);
        $this->alter('DROP COLUMN ' . $this->_database->prepareIdentifier($name));
        unset($this->_columns[$name]); // Update column cache
    }

    /**
     * Internal method to rename a column. Applications must not use this - the
     * column's setName() method is the correct way to rename a column.
     * @param Nada_Column $column Column object
     * @param string $name New name
     * @internal
     **/
    public final function renameColumn($column, $name)
    {
        $this->requireColumn($column->getName());
        $this->forbidColumn($name);

        $this->_renameColumn($column, $name);
        // Update column cache
        $this->_columns[$name] = $column;
        unset($this->_columns[$column->getName()]);
    }

    /**
     * DBMS-specific implementation for renaming a column
     * @param Nada_Column $column Column object
     * @param string $name New name
     **/
    abstract protected function _renameColumn($column, $name);
}
