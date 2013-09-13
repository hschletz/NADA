<?php
/**
 * Interface class for SQLite
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
 * Interface class for SQLite
 *
 * This class overrides methods with SQLite-specific implementations.
 * @package NADA
 */
class Nada_Database_Sqlite extends Nada_Database
{
    /** {@inheritdoc} */
    public function isSqlite()
    {
        return true;
    }

    /** {@inheritdoc} */
    public function getServerVersion()
    {
        $version = $this->query('SELECT SQLITE_VERSION() AS version');
        return $version[0]['version'];
    }

    /** {@inheritdoc} */
    public function timestampFormatIso()
    {
        // SQLite's date and time functions don't use timezone offsets.
        // They support microseconds.
        return 'yyyy-MM-ddTHH:mm:ss.S';
    }

    /** {@inheritdoc} */
    public function timestampFormatPhp()
    {
        // SQLite's date and time functions don't use timezone offsets.
        // They support microseconds.
        return 'Y-m-d\TH:i:s.u';
    }

    /** {@inheritdoc} */
    public function getName()
    {
        // Return full path to the main database file
        $databases = $this->query('PRAGMA DATABASE_LIST');
        foreach ($databases as $database) {
            if ($database['name'] == 'main') {
                return $database['file'];
            }
        }
        throw new LogicException('No entry found by getName()');
    }

    /** {@inheritdoc} */
    public function getTableNames()
    {
        // Fetch table names from sqlite_master, excluding system tables. The
        // name filter works because SQLite forbids names beginning with
        // "sqlite" for regular tables.
        return $this->query(
            "SELECT name AS table_name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );
    }

    /** {@inheritdoc} */
    public function getNativeDatatype($type, $length=null)
    {
        switch ($type) {
            case Nada::DATATYPE_INTEGER:
                return 'INTEGER';
            case Nada::DATATYPE_VARCHAR:
            case Nada::DATATYPE_CLOB:
                return 'TEXT';
            case Nada::DATATYPE_TIMESTAMP:
            case Nada::DATATYPE_DATE:
                if (in_array($type, $this->emulatedDatatypes)) {
                    return 'TEXT';
                } else {
                    throw new DomainException(strtoupper($type) . ' not supported by SQLite and not emulated');
                }
            case Nada::DATATYPE_BOOL:
                if (in_array($type, $this->emulatedDatatypes)) {
                    return 'INTEGER';
                } else {
                    throw new DomainException('BOOL not supported by SQLite and not emulated');
                }
            case Nada::DATATYPE_DECIMAL:
                if (in_array($type, $this->emulatedDatatypes)) {
                    return 'REAL';
                } else {
                    throw new DomainException('DECIMAL not supported by SQLite and not emulated');
                }
            case Nada::DATATYPE_FLOAT:
                return 'REAL';
            default:
                return parent::getNativeDatatype($type, null); // SQLite does not honor $length
        }
    }

    /** {@inheritdoc} */
    protected function _getTablePkDeclaration($primaryKey, $numAutoIncrement)
    {
        // For autoincrement columns, the PK is already specified with the
        // column and must not be set again for the table.
        if ($numAutoIncrement == 0) {
            return parent::_getTablePkDeclaration($primaryKey, $numAutoIncrement);
        } else {
            return '';
        }
    }
}
