<?php
/**
 * Table class for SQLite
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
 * Table class for SQLite
 *
 * This class overrides methods with SQLite-specific implementations.
 * @package NADA
 */
class Nada_Table_Sqlite extends Nada_Table
{
    /** {@inheritdoc} */
    protected function _fetchColumns()
    {
        // Reimplemented because information_schema is not available
        $columns = $this->_database->query('PRAGMA table_info(' . $this->_database->prepareIdentifier($this->_name) . ')');
        foreach ($columns as $column) {
            $column['name'] = strtolower($column['name']);
            $object = Nada_Column::factory($this->_database);
            $object->constructFromTable($this, $column);
            $this->_columns[$column['name']] = $object;

            // Evaluate PK here to avoid extra query
            if ($column['pk']) {
                // Returned ordinal positions start with 1
                $this->_primaryKey[$column['pk'] - 1] = $object;
            }
        }
    }

    /** {@inheritdoc} */
    protected function _fetchConstraints()
    {
        // PK is already evaluated by _fetchColumns()
    }

    /** {@inheritdoc} */
    public function dropColumn($name)
    {
        // Reimplentation due to missing DROP COLUMN support
        $this->alterColumn($name, null, null);
    }

    /** {@inheritdoc} */
    protected function _renameColumn($column, $name)
    {
        $this->alterColumn($column->getName(), 'name', $name);
    }

    /**
     * Internal method to alter a column
     *
     * Since SQLite's ALTER TABLE statement only supports renaming tables and
     * adding columns, this method does everything else:
     *
     * 1. The table is renamed to a unique temporary name.
     * 2. A new table with the original name and altered columns is created.
     * 3. Data is copied to the new table.
     * 4. The old table is dropped.
     *
     * NOTE: Primary keys are preserved, but all other contraints are lost for
     * every column in this table, not just for the altered one.
     *
     * @param string $name Column to be altered
     * @param string $attrib Attribute to be altered or NULL for dropping the column
     * @param mixed $value New value for altered attibute
     * @internal
     */
    public function alterColumn($name, $attrib, $value)
    {
        $this->requireColumn($name);

        // Derive new column specification from existing one
        $newColumns = $this->_columns;
        if ($attrib === null) {
            unset($newColumns[$name]);
        } else {
            $newColumn = $newColumns[$name]->toArray();
            $newColumn[$attrib] = $value;
            $newColumns[$name] = $this->_database->createColumnFromArray($newColumn);
        }

        // Preserve PK
        // TODO preserve other constraints
        // TODO Create methods for reading PKs and constaints.
        $tableName = $this->_database->prepareIdentifier($this->_name);
        $columns = $this->_database->query("PRAGMA table_info($tableName)");
        $pkColumns = array();
        foreach ($columns as $column) {
            // Skip column about to be deleted
            if ($attrib === null and $column['name'] == $name) {
                continue;
            }
            if ($column['pk']) {
                if ($attrib == 'name' and $column['name'] == $name) {
                    // Use new column name for PK
                    $pkColumns[] = $this->_database->prepareIdentifier($value);
                } else {
                    $pkColumns[] = $this->_database->prepareIdentifier($column['name']);
                }
            }
        }
        $pkColumns = implode(', ', $pkColumns);

        // Create savepoint instead of starting a transaction because calling
        // code might already have started a transaction.
        $this->_database->exec('SAVEPOINT ' . __FUNCTION__);

        // Rename table to a unique temporary name
        $tmpTableName = $this->_name;
        $tableNames = $this->_database->getTableNames();
        do {
            $tmpTableName .= '_bak';
        } while (in_array($tmpTableName, $tableNames));
        // Use quoteIdentifier() instead of prepareIdentifier() because the
        // temporary name is not identified as a keyword.
        $tmpTableName = $this->_database->quoteIdentifier($tmpTableName);
        $this->_database->exec("ALTER TABLE $tableName RENAME TO $tmpTableName");

        // Create table with new column specifications
        $this->_database->clearCache($this->_name);
        $this->_database->createTable($this->_name, $newColumns, $pkColumns);

        // Copy data from old table
        $columnsOld = array();
        $columnsNew = array();
        foreach ($newColumns as $column) {
            if ($attrib == 'name' and $column->getName() == $value) {
                // Special treatment for renamed columns
                $columnsOld[] = $this->_database->prepareIdentifier($name);
                $columnsNew[] = $this->_database->prepareIdentifier($value);
            } else {
                $columnsOld[] = $this->_database->prepareIdentifier($column->getName());
                $columnsNew[] = $this->_database->prepareIdentifier($column->getName());
            }
        }
        $columnsOld = implode (', ', $columnsOld);
        $columnsNew = implode (', ', $columnsNew);
        $this->_database->exec(
            "INSERT INTO $tableName ($columnsNew) SELECT $columnsOld FROM $tmpTableName"
        );

        // Drop old table
        $this->_database->exec("DROP TABLE $tmpTableName");

        // Release savepoint
        $this->_database->exec('RELEASE ' . __FUNCTION__);

        // Update column cache
        $this->_columns = $newColumns;
    }
}