<?php
/**
 * Table class for MySQL
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

use Nada\Column\AbstractColumn as Column;

/**
 * Table class for MySQL
 *
 * This class overrides methods with MySQL-specific implementations.
 * @package NADA
 */
class Mysql extends AbstractTable
{
    /**
     * Table engine, managed by getEngine()/setEngine()
     */
    protected $_engine;

    /** {@inheritdoc} */
    function __construct($database, $name)
    {
        $this->_informationSchemaColumns[] = 'extra';
        $this->_informationSchemaColumns[] = 'column_comment';
        parent::__construct($database, $name);
    }

    /** {@inheritdoc} */
    protected function _fetchComment()
    {
        $result = $this->_database->query(
            'SHOW TABLE STATUS LIKE ?',
            $this->_name
        );
        return $result[0]['comment'];
    }

    /** {@inheritdoc} */
    protected function _setComment($comment)
    {
        $this->alter('COMMENT = ' . $this->_database->prepareValue($comment, Column::TYPE_VARCHAR));
        return true;
    }

    /** {@inheritdoc} */
    protected function _renameColumn($column, $name)
    {
        $this->alter(
            'CHANGE ' .
            $this->_database->prepareIdentifier($column->getName()) .
            ' ' .
            $this->_database->prepareIdentifier($name) .
            ' ' .
            $column->getDefinition()
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

        $this->alter(
            sprintf(
                '%sADD PRIMARY KEY(%s)',
                $this->_primaryKey ? 'DROP PRIMARY KEY, ' : '',
                implode(', ', $columns)
            )
        );

        // Rebuild stored PK
        $this->_primaryKey = array();
        foreach ($columns as $column) {
            $this->_primaryKey[$column] = $this->_columns[$column];
        }
    }

    /** {@inheritdoc} */
    protected function _fetchIndexes()
    {
        $columns = $this->_database->query(
            'SELECT index_name, column_name, non_unique FROM information_schema.statistics ' .
            'WHERE table_schema = ? AND table_name = ? AND index_name != \'PRIMARY\' ORDER BY seq_in_index',
            array($this->_database->getName(), $this->_name)
        );
        // Group the result set by index name, aggregate columns
        $indexes = array();
        foreach ($columns as $column) {
            $indexes[$column['index_name']]['columns'][] = strtolower($column['column_name']);
            $indexes[$column['index_name']]['unique'] = !$column['non_unique']; // Same for every row in the index
        }
        // Create index objects
        foreach ($indexes as $name => $index) {
            $this->_indexes[$name] = new \Nada\Index($name, $index['columns'], $index['unique']);
        }
    }

    /** {@inheritdoc} */
    public function toArray($assoc=false)
    {
        $data = parent::toArray($assoc);
        $data['mysql']['engine'] = $this->getEngine();
        return $data;
    }

    /**
     * Set character set and convert data to new character set
     * @param string $charset Character set known to MySQL server
     * @return void
     **/
    public function setCharset($charset)
    {
        $this->_database->exec(
            'ALTER TABLE ' .
            $this->_database->prepareIdentifier($this->_name) .
            ' CONVERT TO CHARACTER SET ' .
            $this->_database->prepareValue($charset, Column::TYPE_VARCHAR)
        );
    }

    /**
     * Retrieve table engine
     * 
     * @return string
     */
    public function getEngine()
    {
        if (!$this->_engine) {
            $table = $this->_database->query('SHOW TABLE STATUS LIKE ?', $this->_name);
            $this->_engine = $table[0]['engine'];
        }
        return $this->_engine;
    }

    /**
     * Set table engine
     * @param string $engine New table engine (MyISAM, InnoDB etc.)
     * @throws RuntimeException if $engine is not recognized by the server
     **/
    public function setEngine($engine)
    {
        $this->_database->exec(
            'ALTER TABLE ' .
            $this->_database->prepareIdentifier($this->_name) .
            ' ENGINE = ' .
            $this->_database->prepareValue($engine, Column::TYPE_VARCHAR)
        );
        // MySQL ignores invalid engine names. Check explicitly.
        // The getEngine() also implicitly updates $_engine.
        if (strcasecmp($this->getEngine(), $engine) != 0) {
            throw new \RuntimeException('Unsupported table engine: ' . $engine);
        }
    }
}
