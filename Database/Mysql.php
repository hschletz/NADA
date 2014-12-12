<?php
/**
 * Interface class for MySQL
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
 * Interface class for MySQL
 *
 * This class overrides methods with MySQL-specific implementations.
 * @package NADA
 */
class Nada_Database_Mysql extends Nada_Database
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
    public function timestampFormatIso()
    {
        // MySQL would accept a timezone, but ignore it and issue a warning.
        // Return format without timezone to avoid the warning.
        return 'yyyy-MM-ddTHH:mm:ss';
    }

    /** {@inheritdoc} */
    public function timestampFormatPhp()
    {
        // MySQL would accept a timezone, but ignore it and issue a warning.
        // Return format without timezone to avoid the warning.
        return 'Y-m-d\TH:i:s';
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
    public function getNativeDatatype($type, $length=null)
    {
        switch ($type) {
            case Nada::DATATYPE_INTEGER:
                if ($length == 8) {
                    return 'TINYINT';
                }
                return parent::getNativeDatatype($type, $length);
            case Nada::DATATYPE_TIMESTAMP:
                return 'DATETIME';
            case Nada::DATATYPE_BOOL:
                if (in_array(Nada::DATATYPE_BOOL, $this->emulatedDatatypes)) {
                    return 'TINYINT';
                } else {
                    throw new DomainException('BOOL not supported by MySQL and not emulated');
                }
            case Nada::DATATYPE_CLOB:
                return 'LONGTEXT';
            case Nada::DATATYPE_BLOB:
                return 'LONGBLOB';
            default:
                return parent::getNativeDatatype($type, $length);
        }
    }
}
