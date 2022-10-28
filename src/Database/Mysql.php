<?php
/**
 * Interface class for MySQL
 *
 * Copyright (C) 2011-2022 Holger Schletz <holger.schletz@web.de>
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

namespace Nada\Database;

use Nada\Column\AbstractColumn as Column;

/**
 * Interface class for MySQL
 *
 * This class overrides methods with MySQL-specific implementations.
 */
class Mysql extends AbstractDatabase
{

    /** {@inheritdoc} */
    function __construct($link){
        parent::__construct($link);
        $this->_tableSchema = $this->getName();
    }

    /** {@inheritdoc} */
    public function isMysql()
    {
        return true;
    }

    /** {@inheritdoc} */
    public function getServerVersion()
    {
        $version = $this->query('SELECT VERSION() AS version');
        $version = $version[0]['version'];

        // Chop off optional suffix ("x.y.z-suffix")
        $endpos = strpos($version, '-');
        if ($endpos) {
            return substr($version, 0, $endpos);
        } else {
            return $version;
        }
    }

    /** {@inheritdoc} */
    public function setTimezone($timezone = null)
    {
        if ($timezone === null) {
            $timezone = '+00:00';
        }
        $this->exec('SET time_zone = ' . $this->prepareValue($timezone, Column::TYPE_VARCHAR));
    }

    /** {@inheritdoc} */
    public function convertTimestampColumns()
    {
        $columns = $this->query(
            'SELECT table_name, column_name FROM information_schema.columns WHERE data_type = ? AND table_schema = ?',
            array('timestamp', $this->_tableSchema)
        );
        if ($columns) {
            $emulatedDatatypes = $this->emulatedDatatypes; // preserve
            if (!in_array(Column::TYPE_TIMESTAMP, $emulatedDatatypes)) {
                // Emulate datatype temporarily to allow manipulation
                $this->emulatedDatatypes[] = Column::TYPE_TIMESTAMP;
            }
            foreach ($columns as $column) {
                $column = $this->getTable($column['table_name'])->getColumn(strtolower($column['column_name']));
                if ($column->getDefault() == 'CURRENT_TIMESTAMP') {
                    $column->setDefault(null);
                }
                $column->setDatatype(Column::TYPE_TIMESTAMP);
            }
            $this->emulatedDatatypes = $emulatedDatatypes; // restore
        }
        return count($columns);
    }

    /** {@inheritdoc} */
    protected function _quoteIdentifier($identifier)
    {
        // Use backtick that works independently of ANSI_QUOTES setting
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /** {@inheritdoc} */
    public function setStrictMode()
    {
        $modes = implode(
            ',',
            array(
                'STRICT_ALL_TABLES', // Always abort INSERT/UPDATE on error, do not substitute BS values
                'ANSI_QUOTES', // Double quotes delimit column identifiers, not literals
                'ERROR_FOR_DIVISION_BY_ZERO', // Error on INSERT/UPDATE
                'NO_AUTO_VALUE_ON_ZERO', // Allow explicit 0 for serials
                'NO_BACKSLASH_ESCAPES', // Treat backslashes literally (not as escape character)
                'NO_ZERO_DATE', // Forbid '0000-00-00'
                'NO_ZERO_IN_DATE', // Forbid stuff like '2011-10-00'
                'ONLY_FULL_GROUP_BY', // Non-aggregate columns must be set explicitly in GROUP BY clause
                'PIPES_AS_CONCAT', // || is string concatenation, not logical OR
                'REAL_AS_FLOAT', // REAL is single precision, not double
            )
        );
        $this->exec('SET SESSION sql_mode=?', $modes);
    }

    /** {@inheritdoc} */
    public function getName()
    {
        if (!$this->_name) {
            $result = $this->query(
                'SELECT DATABASE() AS catalog_name'
            );
            $this->_name = $result[0]['catalog_name'];
        }
        return $this->_name;
    }

    /** {@inheritdoc} */
    public function getNativeDatatype($type, $length=null, $cast=false)
    {
        if ($cast) {
            switch ($type) {
                case Column::TYPE_INTEGER:
                    return 'SIGNED';
                case Column::TYPE_VARCHAR:
                    if ($length === null) {
                        return 'CHAR';
                    } elseif (ctype_digit((string) $length)) {
                        return "CHAR($length)";
                    } else {
                        throw new \InvalidArgumentException('Invalid length: ' . $length);
                    }
                case Column::TYPE_TIMESTAMP:
                    return 'DATETIME';
                case Column::TYPE_BOOL:
                    throw new \DomainException('Values cannot be cast to BOOL');
                case Column::TYPE_CLOB:
                    return 'CHAR';
                case Column::TYPE_BLOB:
                    return 'BINARY';
                case Column::TYPE_DECIMAL:
                    return str_replace('NUMERIC', 'DECIMAL', parent::getNativeDatatype($type, $length, $cast));
                case Column::TYPE_FLOAT:
                    return 'DECIMAL';
                default:
                    return parent::getNativeDatatype($type, $length, $cast);
            }
        } else {
            switch ($type) {
                case Column::TYPE_INTEGER:
                    if ($length == 8) {
                        return 'TINYINT';
                    }
                    return parent::getNativeDatatype($type, $length, $cast);
                case Column::TYPE_TIMESTAMP:
                    return 'DATETIME';
                case Column::TYPE_BOOL:
                    if (in_array(Column::TYPE_BOOL, $this->emulatedDatatypes)) {
                        return 'TINYINT';
                    } else {
                        throw new \DomainException('BOOL not supported by MySQL and not emulated');
                    }
                case Column::TYPE_CLOB:
                    return 'LONGTEXT';
                case Column::TYPE_BLOB:
                    return 'LONGBLOB';
                default:
                    return parent::getNativeDatatype($type, $length, $cast);
            }
        }
    }
}
