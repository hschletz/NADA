<?php
/**
 * Abstract table class
 *
 * Copyright (C) 2011-2018 Holger Schletz <holger.schletz@web.de>
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
 */

namespace Nada\Table;

/**
 * Abstract table class
 *
 * This is the base class for providing a unified interface to database tables.
 * It is not intended to be instantiated directly, but through one of the
 * Nada\Database\AbstractDatabase methods.
 */
abstract class AbstractTable
{
    /**
     * Database link
     * @var \Nada\Database\AbstractDatabase
     */
    protected $_database;

    /**
     * Table name
     * @var string
     */
    protected $_name;

    /**
     * Table comment
     * @var string
     */
    protected $_comment;

    /**
     * Flag indicating whether comment has already been fetched
     * @var bool
     */
    protected $_commentFetched = false;

    /**
     * This table's columns
     *
     * This is an associative array populated by {@link _fetchColumns()}.
     * The column name is the key, and the value is a \Nada\Column\AbstractColumn derived object.
     * @var array
     */
    protected $_columns;

    /**
     * This table's primary key
     *
     * This is always an array, even for single column primary keys. The columns
     * are arranged in the correct order.
     *
     * @var \Nada\Column\AbstractColumn[]
     */
    protected $_primaryKey;

    /**
     * This table's indexes
     *
     * This is an associative array populated by _fetchIndexes().
     * The index name is the key, and the value is a \Nada\Index object.
     * @var array
     */
    protected $_indexes = array();

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
     *
     * @param \Nada\Database\AbstractDatabase $database Database interface
     * @param string $name Table name
     * @throws \RuntimeException if table does not exist
     */
    function __construct($database, $name)
    {
        $this->_database = $database;
        $this->_name = $name;

        $this->_fetchColumns();
        if (empty($this->_columns)) {
            throw new \RuntimeException('Table does not exist: ' . $name);
        }
        $this->_fetchConstraints();
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
     * Return table comment
     * @return string table comment
     */
    public function getComment()
    {
        if (!$this->_commentFetched) {
            $this->_comment = $this->_fetchComment();
            $this->_commentFetched = true;
        }
        return $this->_comment;
    }

    /**
     * Set table comment
     * @param string $comment table comment
     */
    public function setComment($comment)
    {
        if ($comment != $this->getComment() and $this->_setComment($comment))
        {
            $this->_comment = $comment;
        }
    }

    /**
     * DBMS specific method to fetch table comment
     * @return string table comment
     */
    abstract protected function _fetchComment();

    /**
     * DBMS specific method to set table comment
     * @param string $comment table comment
     * @return bool TRUE if a comment can be set on the table
     */
    abstract protected function _setComment($comment);

    /**
     * Return database interface
     *
     * @return \Nada\Database\AbstractDatabase Database interface
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
     * make this functional, subclasses must set
     * \Nada\Database\AbstractDatabase::$_tableSchema and, if necessary, extend
     * $_informationSchemaColumns with DBMS-specific columns.
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
            $object = $this->_database->createColumnObject();
            $object->constructFromTable($this, $column);
            $this->_columns[$column['column_name']] = $object;
        }
    }

    /**
     * Fetch constraint information from the database
     *
     * Invoked by the constructor, the default implementation queries
     * information_schema.table_constraints and information_schema.key_column_usage.
     * To make this functional, subclasses must set
     * \Nada\Database\AbstractDatabase::$_tableSchema.
     *
     * Only primary keys are recognized for now. All other constraints are
     * ignored.
     *
     * @todo Recognize other constraint types
     */
    protected function _fetchConstraints()
    {
        $constraints = $this->_database->query(
            <<<EOT
            SELECT kcu.column_name
            FROM information_schema.key_column_usage kcu
            JOIN information_schema.table_constraints tc
            USING (table_schema, table_name, constraint_schema, constraint_name)
            WHERE tc.constraint_type = 'PRIMARY KEY'
            AND tc.table_schema = ?
            AND LOWER(tc.table_name) = ?
            ORDER BY kcu.ordinal_position
EOT
            ,
            array(
                $this->_database->getTableSchema(),
                $this->_name,
            )
        );
        foreach ($constraints as $constraint) {
            $this->_primaryKey[] = $this->_columns[strtolower($constraint['column_name'])];
        }
    }

    /**
     * Return a single column
     * @param $name Column name
     * @return \Nada\Column\AbstractColumn Column interface
     */
    public function getColumn($name)
    {
        $this->requireColumn($name);
        return $this->_columns[$name];
    }

    /**
     * Return all columns
     * @return array Array of \Nada\Column\AbstractColumn objects with column names as keys
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    /**
     * Force presence of column
     * @param string $name Column name to check for, must be lowercase
     * @throws \RuntimeException if column does not exist
     * @throws \DomainException if $name is not lowercase
     */
    public function requireColumn($name)
    {
        if ($name != strtolower($name)) {
            throw new \DomainException('Column name must be lowercase: ' . $name);
        }

        if (!isset($this->_columns[$name])) {
            throw new \RuntimeException('Undefined column: ' . $this->_name . '.' . $name);
        }
    }

    /**
     * Force Absence of column
     * @param string $name Column name to check for, must be lowercase
     * @throws \RuntimeException if column exists
     * @throws \DomainException if $name is not lowercase
     */
    public function forbidColumn($name)
    {
        if ($name != strtolower($name)) {
            throw new \DomainException('Column name must be lowercase: ' . $name);
        }

        if (isset($this->_columns[$name])) {
            throw new \RuntimeException('Already defined column: ' . $this->_name . '.' . $name);
        }
    }

    /**
     * Return primary key
     *
     * @return \Nada\Column\AbstractColumn[]
     */
    public function getPrimaryKey()
    {
        return $this->_primaryKey;
    }

    /**
     * Set primary key
     *
     * Altering a primary key is a critical operation that should be done with
     * care and avoided if possible. The content of the new PK's columns is not
     * checked - if it does not meet the requirement for a PK, this operation
     * will fail.
     *
     * @param string|string[] $columns PK column(s)
     */
    abstract public function setPrimaryKey($columns);

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
     * @param string $type Datatype, one of \Nada\Column\AbstractColumn's TYPE_* constants
     * @param mixed $length Optional length specification
     * @param bool $notnull NOT NULL constraint (default: FALSE)
     * @param mixed $default Default value (DEFAULT: NULL)
     * @param bool $autoIncrement Auto increment property (default: FALSE)
     * @param string $comment Column comment (default: NULL)
     * @return \Nada\Column\AbstractColumn object describing the generated column
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
     * Add a column using a column object
     *
     * @param \Nada\Column\AbstractColumn $column Column object
     * @return \Nada\Column\AbstractColumn object describing the generated column
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
     * @param string $name Column name
     */
    public function dropColumn($name)
    {
        $this->requireColumn($name);
        $this->alter('DROP COLUMN ' . $this->_database->prepareIdentifier($name));
        unset($this->_columns[$name]); // Update column cache
        $this->_updateConstraints($name, null);
    }

    /**
     * Internal method to rename a column. Applications must not use this - the
     * column's setName() method is the correct way to rename a column.
     *
     * @param \Nada\Column\AbstractColumn $column Column object
     * @param string $name New name
     * @internal
     **/
    public final function renameColumn($column, $name)
    {
        $oldName = $column->getName();

        $this->requireColumn($oldName);
        $this->forbidColumn($name);

        $this->_renameColumn($column, $name);
        // Update column cache
        $this->_columns[$name] = $column;
        unset($this->_columns[$oldName]);
        $this->_updateConstraints($oldName, $name);
    }

    /**
     * DBMS-specific implementation for renaming a column
     *
     * @param \Nada\Column\AbstractColumn $column Column object
     * @param string $name New name
     **/
    abstract protected function _renameColumn($column, $name);

    /**
     * Update constraints after renaming or dropping a column
     *
     * This updates affected column names in all constraints and must be called
     * throm the renameColumn() and dropColumn() implementation.
     *
     * @param string $oldColumnName Column name before renaming/dropping
     * @param string $newColumnName Column name after renaming, NULL for dropped column
     */
    protected function _updateConstraints($oldColumnName, $newColumnName)
    {
        $columnIndex = null;
        foreach ($this->_primaryKey as $index => $column) {
            if ($oldColumnName == $column->getName()) {
                $columnIndex = $index;
                break;
            }
        }
        if ($columnIndex !== null) {
            if ($newColumnName) {
                $this->_primaryKey[$columnIndex] = $this->_columns[$newColumnName];
            } else {
                unset($this->_primaryKey[$columnIndex]);
                $this->_primaryKey = array_values($this->_primaryKey);
            }
        }
    }

    /**
     * Create an index
     *
     * The presence of an existing index with the same name or column set is not checked.
     *
     * @param string $name Index name
     * @param mixed $columns Column name or array of column names
     * @param bool $unique Create unique index, default: false
     */
    public function createIndex($name, $columns, $unique=false)
    {
        if (!is_array($columns)) {
            $columns = array($columns);
        }
        foreach ($columns as &$column) {
            $column = $this->_database->prepareIdentifier($column);
        }
        $this->_database->exec(
            sprintf(
                'CREATE %s INDEX %s ON %s (%s)',
                $unique ? 'UNIQUE' : '',
                $this->_database->prepareIdentifier($name),
                $this->_database->prepareIdentifier($this->_name),
                implode(', ', $columns)
            )
        );
        // Reset index cache to force re-read by next getIndexes() invokation
        $this->_indexes = array();
    }

    /**
     * Get all indexes for this table
     *
     * Implicit indexes, as for the primary key, are not included in the result.
     *
     * @return \Nada\Index[]
     */
    public function getIndexes()
    {
        if (empty($this->_indexes)) {
            $this->_fetchIndexes();
        }
        return $this->_indexes;
    }

    /**
     * Fetch index definitions
     *
     * This is to be implemented by a subclass. The method must add all relevant
     * indexes to $_indexes.
     */
    abstract protected function _fetchIndexes();

    /**
     * Check for presence of index with given properties, ignoring index name
     *
     * @param mixed $columns Column name or array of column names
     * @param bool $unique Unique index, default: false
     * @return bool
     */
    public function hasIndex($columns, $unique=false)
    {
        if (!is_array($columns)) {
            $columns = array($columns);
        }
        foreach ($this->getIndexes() as $index) {
            if ($index->isUnique() == $unique and $index->getColumns() == $columns) {
                return true;
            }
        }
        return false;
    }

    /**
     * Export table data to an associative array
     *
     * The returned array has the following elements:
     *
     * - **name**: the table name
     * - **columns**: array of columns (numeric or associative, depending on
     *   $assoc). See \Nada\Column\AbstractColumn::toArray() for a description
     *   of keys.
     * - **primary_key**: array of column names for primary key
     * - **indexes**: array of indexes (numeric or associative, depending on
     *   $assoc). See \Nada\Index::toArray() for a description of keys.
     * - **mysql**: (MySQL only) array with MySQL-specific data:
     *   - **engine**: table engine
     *
     * @param bool $assoc
     * @return array
     */
    public function toArray($assoc=false)
    {
        $data = array(
            'name' => $this->_name,
            'comment' => $this->getComment(),
        );
        foreach ($this->_columns as $name => $column) {
            if ($assoc) {
                $data['columns'][$name] = $column->toArray();
            } else {
                $data['columns'][] = $column->toArray();
            }
        }
        if (!empty($this->_primaryKey)) {
            foreach ($this->_primaryKey as $name => $column) {
                $data['primary_key'][] = $column->getName();
            }
        }
        foreach ($this->getIndexes() as $name => $index) {
            if ($assoc) {
                $data['indexes'][$name] = $index->toArray();
            } else {
                $data['indexes'][] = $index->toArray();
            }
        }
        return $data;
    }
}
