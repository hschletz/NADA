<?php
/**
 * Table class for PostgreSQL
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

namespace Nada\Table;

/**
 * Table class for PostgreSQL
 *
 * This class overrides methods with PostgreSQL-specific implementations.
 * @package NADA
 */
class Pgsql extends AbstractTable
{

    /** {@inheritdoc} */
    function __construct($database, $name)
    {
        $this->_informationSchemaColumns[] = 'COL_DESCRIPTION(table_name::REGCLASS::OID, ordinal_position) AS comment';
        parent::__construct($database, $name);
    }

    /** {@inheritdoc} */
    protected function _fetchComment()
    {
        $result = $this->_database->query(
            "SELECT OBJ_DESCRIPTION(CAST(? AS REGCLASS), 'pg_class') AS comment",
            $this->_name
        );
        return $result[0]['comment'];
    }

    /** {@inheritdoc} */
    protected function _setComment($comment)
    {
        $this->_database->exec(
            sprintf(
                'COMMENT ON TABLE %s IS %s',
                $this->_database->prepareIdentifier($this->_name),
                $this->_database->prepareValue($comment, \Nada::DATATYPE_VARCHAR)
            )
        );
        return true;
    }

    /** {@inheritdoc} */
    public function addColumnObject($column)
    {
        // PostgreSQL creates columns without comment. Adjust $column so that
        // setComment() knows that it must still be added.
        $comment = $column->getComment();
        $column->setComment(null);
        $newColumn = parent::addColumnObject($column);
        if ($comment) {
            $newColumn->setComment($comment);
            $column->setComment($comment);
        }
        return $newColumn;
    }

    /** {@inheritdoc} */
    protected function _renameColumn($column, $name)
    {
        $this->alter(
            'RENAME COLUMN ' .
            $this->_database->prepareIdentifier($column->getName()) .
            ' TO ' .
            $this->_database->prepareIdentifier($name)
        );
    }

    /** {@inheritdoc} */
    public function setPrimaryKey($columns)
    {
        if (!is_array($columns)) {
            $columns = array($columns);
        }
        foreach ($columns as &$column) {
            $column = $this->_database->prepareIdentifier($column);
        }
        unset ($column);

        $oldPk = $this->_database->query(
            'SELECT indexrelid::regclass::text FROM pg_index ' .
            ' WHERE indrelid = CAST(? AS regclass) AND indisprimary = true',
            $this->_name
        );
        if ($oldPk) {
            $this->alter('DROP CONSTRAINT ' . $this->_database->quoteIdentifier($oldPk[0]['indexrelid']));
        }
        $this->alter(sprintf('ADD PRIMARY KEY(%s)', implode(', ', $columns)));

        // Rebuild stored PK
        $this->_primaryKey = array();
        foreach ($columns as $column) {
            $this->_primaryKey[$column] = $this->_columns[$column];
        }
    }

    /** {@inheritdoc} */
    protected function _fetchIndexes()
    {
        // Get all indexes for this table
        $indexes = $this->_database->query(
            'SELECT indexrelid::regclass::text, indisunique, indkey FROM pg_index ' .
            'WHERE indrelid = CAST(? AS regclass) AND indisprimary = false',
            $this->_name
        );
        foreach ($indexes as $index) {
            // 'indkey' contains space-separated list of ordinal column positions.
            // Extract names and positions for relevant columns.
            $columns = $this->_database->query(
                'SELECT column_name, ordinal_position FROM information_schema.columns ' .
                'WHERE table_catalog = ? AND table_name = ? ' .
                'AND ordinal_position IN(' . strtr($index['indkey'], ' ', ',') . ')',
                array($this->_database->getName(), $this->_name)
            );
            $positions = array();
            foreach ($columns as $column) {
                $positions[$column['ordinal_position']] = $column['column_name'];
            }

            // Rearrange columns in the original order.
            $columnNames = array();
            foreach (explode(' ', $index['indkey']) as $position) {
                $columnNames[] = $positions[$position];
            }

            // Create index object.
            $this->_indexes[$index['indexrelid']] = new \Nada_Index(
                $index['indexrelid'],
                $columnNames,
                $index['indisunique']
            );
        }
    }
}
