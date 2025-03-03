<?php

namespace Nada\Table;

/**
 * Table class for SQLite
 *
 * This class overrides methods with SQLite-specific implementations.
 */
class Sqlite extends AbstractTable
{
    /** {@inheritdoc} */
    protected function _fetchColumns()
    {
        // Reimplemented because information_schema is not available
        $columns = $this->_database->query('PRAGMA table_info(' . $this->_database->prepareIdentifier($this->_name) . ')');
        $pkColumnCount = 0;
        foreach ($columns as $column) {
            if ($column['pk']) {
                $pkColumnCount++;
            }
        }
        foreach ($columns as $column) {
            $column['name'] = strtolower($column['name']);
            $column['pkColumnCount'] = $pkColumnCount;
            $object = $this->_database->createColumnObject();
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
    protected function _fetchComment(): ?string
    {
        return null;
    }

    /** {@inheritdoc} */
    protected function _setComment($comment)
    {
        return false;
    }

    /** {@inheritdoc} */
    public function dropColumn($name)
    {
        // Reimplentation due to missing DROP COLUMN support
        $this->alterColumn($name, null, null);
        $this->_updateConstraints($name, null);
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
     * @param ?string $attrib Attribute to be altered or NULL for dropping the column
     * @param mixed $value New value for altered attibute
     * @internal
     */
    public function alterColumn($name, ?string $attrib, $value)
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
        $pkColumns = array();
        if ($this->_primaryKey) {
            foreach ($this->_primaryKey as $column) {
                if ($name == $column->getName()) {
                    if ($attrib === null) {
                        // Skip column about to be deleted
                        continue;
                    } elseif ($attrib == 'name') {
                        // Use new column name for PK
                        $pkColumns[] = $value;
                        continue;
                    }
                }
                $pkColumns[] = $column->getName();
            }
        }

        // Start exclusive operations
        $savepoint = uniqid(__FUNCTION__);
        $this->_database->exec('SAVEPOINT ' . $savepoint);
        $this->_database->exec('PRAGMA locking_mode = EXCLUSIVE');

        $tmpTableName = $this->_renameToTmp();

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
        $columnsOld = implode(', ', $columnsOld);
        $columnsNew = implode(', ', $columnsNew);
        $tableName = $this->_database->prepareIdentifier($this->_name);
        $this->_database->exec(
            "INSERT INTO $tableName ($columnsNew) SELECT $columnsOld FROM $tmpTableName"
        );

        // Clean up
        $this->_database->exec("DROP TABLE $tmpTableName");
        $this->_database->exec('PRAGMA locking_mode = NORMAL');
        $this->_database->exec('RELEASE ' . $savepoint);

        // Update column cache (update keys as well in case of renamed columns)
        $this->_columns = array();
        foreach ($newColumns as $column) {
            $this->_columns[$column->getName()] = $column;
        }
    }

    /**
     * {@inheritdoc}
     *
     * There is no direct way to alter a PK. This method does it indirectly:
     *
     * 1. The table is renamed to a unique temporary name.
     * 2. A new table with the original name and structure and altered PK is created.
     * 3. Data is copied to the new table.
     * 4. The old table is dropped.
     *
     * NOTE: All other constraints are lost for every column in this table.
     */
    public function setPrimaryKey($columns)
    {
        // Start exclusive operations
        $savepoint = uniqid(__FUNCTION__);
        $this->_database->exec('SAVEPOINT ' . $savepoint);
        $this->_database->exec('PRAGMA locking_mode = EXCLUSIVE');

        $tmpTableName = $this->_renameToTmp();

        // Create table with new column specifications
        $this->_database->clearCache($this->_name);
        $this->_database->createTable($this->_name, $this->_columns, $columns);

        // Copy data from old table
        $columnNames = array_keys($this->_columns);
        foreach ($columnNames as &$column) {
            $column = $this->_database->prepareIdentifier($column);
        }
        unset($column);
        $columnNames = implode(', ', $columnNames);
        $tableName = $this->_database->prepareIdentifier($this->_name);
        $this->_database->exec(
            "INSERT INTO $tableName ($columnNames) SELECT $columnNames FROM $tmpTableName"
        );

        // Clean up
        $this->_database->exec("DROP TABLE $tmpTableName");
        $this->_database->exec('PRAGMA locking_mode = NORMAL');
        $this->_database->exec('RELEASE ' . $savepoint);

        // Rebuild stored PK
        if (!is_array($columns)) {
            $columns = array($columns);
        }
        $this->_primaryKey = array();
        foreach ($columns as $column) {
            $this->_primaryKey[$column] = $this->_columns[$column];
        }
    }

    /**
     * Rename table to a unique temporary name
     *
     * @return string New table name
     */
    protected function _renameToTmp()
    {
        $tmpTableName = $this->_name;
        $tableNames = $this->_database->getTableNames();
        do {
            $tmpTableName .= '_bak';
        } while (in_array($tmpTableName, $tableNames));
        // Use quoteIdentifier() instead of prepareIdentifier() because the
        // temporary name is not identified as a keyword.
        $tmpTableName = $this->_database->quoteIdentifier($tmpTableName);
        $this->alter("RENAME TO $tmpTableName");
        return $tmpTableName;
    }

    /** {@inheritdoc} */
    protected function _fetchIndexes()
    {
        // Fetch list of index names and their UNIQUE attributes
        $indexes = $this->_database->query(
            'PRAGMA index_list(' . $this->_database->prepareIdentifier($this->_name) . ')'
        );
        $unique = array();
        foreach ($indexes as $index) {
            $unique[$index['name']] = $index['unique'];
        }

        // The list above still contains autogenerated indexes, as for PK. These
        // have no 'sql' value in the sqlite_master table  which can be used to
        // filter them out.
        $indexNames = $this->_database->query(
            'SELECT name FROM sqlite_master WHERE sql IS NOT NULL AND type=\'index\' AND tbl_name = ?',
            $this->_name
        );
        foreach ($indexNames as $name) {
            $name = $name['name'];
            // Get the columns for each index.
            $index = $this->_database->query(
                'PRAGMA index_info(' . $this->_database->prepareIdentifier($name) . ')'
            );
            $columns = array();
            foreach ($index as $column) {
                $columns[$column['seqno']] = $column['name'];
            }
            // Ordering of result set is undocumented. Sort manually to preserve order of columns.
            ksort($columns);
            $this->_indexes[$name] = new \Nada\Index($name, $columns, $unique[$name]);
        }
    }
}
