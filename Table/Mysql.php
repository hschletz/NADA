<?php
/**
 * Table class for MySQL
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
 * Table class for MySQL
 *
 * This class overrides methods with MySQL-specific implementations.
 * @package NADA
 */
class Nada_Table_Mysql extends Nada_Table
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
        $this->alter('COMMENT = ' . $this->_database->prepareValue($comment, Nada::DATATYPE_VARCHAR));
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
            $this->_database->prepareValue($charset, Nada::DATATYPE_VARCHAR)
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
            $this->_database->prepareValue($engine, Nada::DATATYPE_VARCHAR)
        );
        // MySQL ignores invalid engine names. Check explicitly.
        // The getEngine() also implicitly updates $_engine.
        if (strcasecmp($this->getEngine(), $engine) != 0) {
            throw new RuntimeException('Unsupported table engine: ' . $engine);
        }
    }
}
