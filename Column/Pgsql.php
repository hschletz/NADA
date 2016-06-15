<?php
/**
 * Column class for PostgreSQL
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

namespace Nada\Column;

/**
 * Column class for PostgreSQL
 *
 * This class overrides methods with PostgreSQL-specific implementations.
 * @package NADA
 */
class Pgsql extends AbstractColumn
{

    /** {@inheritdoc} */
    protected function _parseDatatype($data)
    {
        switch ($data['data_type']) {
            case 'integer':
            case 'smallint':
            case 'bigint':
                $this->_datatype = self::TYPE_INTEGER;
                $this->_length = $data['numeric_precision'];
                break;
            case 'character varying':
                $this->_datatype = self::TYPE_VARCHAR;
                $this->_length = $data['character_maximum_length'];
                break;
            case 'timestamp without time zone':
                $this->_datatype = self::TYPE_TIMESTAMP;
                break;
            case 'date':
                $this->_datatype = self::TYPE_DATE;
                break;
            case 'boolean':
                $this->_datatype = self::TYPE_BOOL;
                break;
            case 'text':
                $this->_datatype = self::TYPE_CLOB;
                break;
            case 'bytea':
                $this->_datatype = self::TYPE_BLOB;
                break;
            case 'numeric':
                $this->_datatype = self::TYPE_DECIMAL;
                $this->_length = $data['numeric_precision'] . ',' . $data['numeric_scale'];
                break;
            case 'double precision':
            case 'real':
                $this->_datatype = self::TYPE_FLOAT;
                $this->_length = $data['numeric_precision'];
                break;
            default:
                throw new \UnexpectedValueException('Unknown PostgreSQL Datatype: ' . $data['data_type']);
        }
    }

    /** {@inheritdoc} */
    protected function _parseDefault($data)
    {
        // Do not use the default from autoincrement columns.
        // See _isAutoIncrement() for an explanation.
        if (!$this->_isAutoIncrement($data))
        {
            parent::_parseDefault($data);
            // Extract value from typed defaults
            if (preg_match("/^('(.*)'|NULL)::$data[data_type]$/", $this->_default, $matches)) {
                if ($matches[1] == 'NULL') {
                    $this->_default = null;
                } else {
                    // String without surrounding quotes. Quote characters
                    // within the string must be unescaped first.
                    $this->_default = str_replace("''", "'", $matches[2]);
                }
            }
        }
    }

    /** {@inheritdoc} */
    protected function _parseAutoIncrement($data)
    {
        $this->_autoIncrement = $this->_isAutoIncrement($data);
    }

    /** {@inheritdoc} */
    protected function _parseComment($data)
    {
        $this->_comment = $data['comment'];
    }

    /**
     * Detect autoincrement property from column data
     *
     * PostgreSQL does not have a real autoincrement type, but implements it via
     * a sequence and nextval() as default. This method detects that construct
     * for 'integer' and 'bigint' columns.
     * @param array $data Column data
     */
    protected function _isAutoIncrement($data)
    {
        if (
            ($data['data_type'] == 'integer' or $data['data_type'] == 'bigint')
            and strpos($data['column_default'], 'nextval(') === 0
        ) {
            return true;
        } else {
            return false;
        }
    }

    /** {@inheritdoc} */
    public function getDefinition()
    {
        if ($this->_autoIncrement) {
            if ($this->_datatype != self::TYPE_INTEGER) {
                throw new \DomainException('Invalid datatype for autoincrement: ' . $this->_datatype);
            }
            if ($this->_default !== null) {
                throw new \DomainException('Invalid default for autoincrement column: ' . $this->_default);
            }
            if ($this->_length == '32' or $this->_length === null) {
                $sql = 'SERIAL';
            } elseif ($this->_length == '64') {
                $sql = 'BIGSERIAL';
            } else {
                throw new \DomainException('Invalid length for autoincrement: ' . $this->_length);
            }
        } else {
            $sql = $this->_database->getNativeDatatype($this->_datatype, $this->_length);
        }

        if ($this->_notnull) {
            $sql .= ' NOT NULL';
        }

        // Explicit DEFAULT NULL is not necessary and leads to ugly 'default NULL::character varying'
        if ($this->_default !== null) {
            $sql .= ' DEFAULT ';
            $sql .= $this->_database->prepareValue($this->_default, $this->_datatype);
        }

        return $sql;
    }

    /** {@inheritdoc} */
    protected function _setDatatype()
    {
        $name = $this->_database->prepareIdentifier($this->_name);
        $datatype = $this->_database->getNativeDatatype($this->_datatype, $this->_length);
        $this->_database->exec(
            'ALTER TABLE ' .
            $this->_database->prepareIdentifier($this->_table->getName()) .
            " ALTER COLUMN $name TYPE $datatype USING CAST($name AS $datatype)"
        );
    }

    /** {@inheritdoc} */
    protected function _setNotNull()
    {
        $this->_table->alter(
            sprintf(
                'ALTER COLUMN %s %s NOT NULL',
                $this->_database->prepareIdentifier($this->_name),
                $this->_notnull ? 'SET' : 'DROP'
            )
        );
    }

    /** {@inheritdoc} */
    protected function _setComment()
    {
        $this->_database->exec(
            'COMMENT ON COLUMN ' .
            $this->_database->prepareIdentifier($this->_table->getName()) .
            '.' .
            $this->_database->prepareIdentifier($this->_name) .
            ' IS ' .
            $this->_database->prepareValue($this->_comment, self::TYPE_VARCHAR)
        );
    }
}
