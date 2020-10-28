<?php
/**
 * Column class for MySQL
 *
 * Copyright (C) 2011-2020 Holger Schletz <holger.schletz@web.de>
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

namespace Nada\Column;

/**
 * Column class for MySQL
 *
 * This class overrides methods with MySQL-specific implementations.
 */
class Mysql extends AbstractColumn
{

    /** {@inheritdoc} */
    protected function _parseDatatype($data)
    {
        switch ($data['data_type']) {
            case 'int':
                $this->_datatype = self::TYPE_INTEGER;
                $this->_length = 32;
                break;
            case 'varchar':
                $this->_datatype = self::TYPE_VARCHAR;
                $this->_length = $data['character_maximum_length'];
                break;
            case 'datetime':
                $this->_datatype = self::TYPE_TIMESTAMP;
                break;
            case 'date':
                $this->_datatype = self::TYPE_DATE;
                break;
            case 'tinytext':
            case 'text':
            case 'mediumtext':
            case 'longtext':
                $this->_datatype = self::TYPE_CLOB;
                break;
            case 'tinyblob':
            case 'blob':
            case 'mediumblob':
            case 'longblob':
                $this->_datatype = self::TYPE_BLOB;
                break;
            case 'tinyint':
                $this->_datatype = self::TYPE_INTEGER;
                $this->_length = 8;
                break;
            case 'smallint':
                $this->_datatype = self::TYPE_INTEGER;
                $this->_length = 16;
                break;
            case 'bigint'://
                $this->_datatype = self::TYPE_INTEGER;
                $this->_length = 64;
                break;
            case 'decimal'://
                $this->_datatype = self::TYPE_DECIMAL;
                $this->_length = $data['numeric_precision'] . ',' . $data['numeric_scale'];
                break;
            case 'float':
                $this->_datatype = self::TYPE_FLOAT;
                $this->_length = 24;
                break;
            case 'double':
                $this->_datatype = self::TYPE_FLOAT;
                $this->_length = 53;
                break;
            case 'timestamp':
                if (in_array(self::TYPE_TIMESTAMP, $this->_database->emulatedDatatypes)) {
                    $this->_datatype = self::TYPE_TIMESTAMP;
                } else {
                    throw new \UnexpectedValueException(
                        'Enable emulation for TYPE_TIMESTAMP to accept MySQL timestamp datatype.'
                    );
                }
                break;
            default:
                throw new \UnexpectedValueException('Unknown MySQL Datatype: ' . $data['data_type']);
        }
    }

    /** {@inheritdoc} */
    protected function _parseAutoIncrement($data)
    {
        if ($data['extra'] == 'auto_increment') {
            $this->_autoIncrement = true;
        } else {
            $this->_autoIncrement = false;
        }
    }

    /** {@inheritdoc} */
    protected function _parseComment($data)
    {
        // If a column has no comment, column_comment is an empty string and
        // must be converted to NULL.
        if (empty($data['column_comment'])) {
            $this->_comment = null;
        } else {
            $this->_comment = $data['column_comment'];
        }
    }

    /** {@inheritdoc} */
    public function getDefinition()
    {
        $sql = $this->_database->getNativeDatatype($this->_datatype, $this->_length);

        if ($this->_notnull) {
            $sql .= ' NOT NULL';
        }

        // For NOT NULL columns, an explicit DEFAULT NULL is not allowed. In
        // that case the default is omitted to achieve a default of NULL.
        if (!($this->_notnull and $this->_default === null)) {
            $sql .= ' DEFAULT ';
            $sql .= $this->_database->prepareValue($this->_default, $this->_datatype);
        }

        if ($this->_autoIncrement) {
            if ($this->_datatype != self::TYPE_INTEGER and $this->_datatype != self::TYPE_FLOAT) {
                throw new \DomainException('Invalid datatype for autoincrement: ' . $this->_datatype);
            }
            if ($this->_default !== null and $this->_default !== 0) {
                throw new \DomainException('Invalid default for autoincrement column: ' . $this->_default);
            }
            $sql .= ' AUTO_INCREMENT';
        }

        if ($this->_comment !== null) {
            $sql .= ' COMMENT ';
            $sql .= $this->_database->prepareValue($this->_comment, self::TYPE_VARCHAR);
        }

        return $sql;
    }

    /**
     * Modify column in the database according to current properties
     * @return void
     **/
    protected function _modify()
    {
        $this->_table->alter(
            'MODIFY ' .
            $this->_database->prepareIdentifier($this->_name) .
            ' ' .
            $this->getDefinition()
        );
    }

    /** {@inheritdoc} */
    protected function _setDatatype()
    {
        $this->_modify();
    }

    /** {@inheritdoc} */
    protected function _setNotNull()
    {
        $this->_modify();
    }

    /** {@inheritdoc} */
    protected function _setComment()
    {
        $this->_modify();
    }

    /** {@inheritdoc} */
    protected function _isDifferent($oldSpec, $newSpec)
    {
        if (
            array_key_exists('type', $newSpec) and
            $newSpec['type'] == self::TYPE_BOOL and
            $oldSpec['type'] == self::TYPE_INTEGER
        ) {
            // Booleans are detected as 8 bit INTEGER.
            $newSpec['type'] = self::TYPE_INTEGER;
            if (array_key_exists('length', $newSpec)) {
                $newSpec['length'] = 8;
            }
        }
        return parent::_isDifferent($oldSpec, $newSpec);
    }
}
