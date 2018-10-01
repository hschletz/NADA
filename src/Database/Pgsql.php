<?php
/**
 * Interface class for PostgreSQL
 *
 * Copyright (C) 2011-2018 Holger Schletz <holger.schletz@web.de>
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
 * Interface class for PostgreSQL
 *
 * This class overrides methods with PostgreSQL-specific implementations.
 */
class Pgsql extends AbstractDatabase
{

    /** {@inheritdoc} */
    protected $_tableSchema = 'public';

    /** {@inheritdoc} */
    public function isPgsql()
    {
        return true;
    }

    /** {@inheritdoc} */
    public function getServerVersion()
    {
        $version = $this->query('SELECT VERSION() AS version');
        $version = $version[0]['version'];

        // Extract second part from version string ("PostgreSQL x.y.z on ...")
        $startpos = strpos($version, ' ') + 1;
        $endpos = strpos($version, ' ', $startpos);
        return substr($version, $startpos, $endpos - $startpos);
    }

    /** {@inheritdoc} */
    public function iLike()
    {
        return ' ILIKE ';
    }

    /** {@inheritdoc} */
    public function setTimezone($timezone = null)
    {
        if ($timezone === null) {
            $timezone = 'UTC';
        }
        $this->exec('SET timezone TO ' . $this->prepareValue($timezone, Column::TYPE_VARCHAR));
    }

    /** {@inheritdoc} */
    public function convertTimestampColumns()
    {
        $columns = $this->query(
            'SELECT table_name, column_name FROM information_schema.columns ' .
            'WHERE datetime_precision != 0 AND table_schema = ? AND data_type IN(?, ?)',
            array($this->_tableSchema, 'timestamp with time zone', 'timestamp without time zone')
        );
        foreach ($columns as $column) {
            $this->getTable($column['table_name'])
                 ->getColumn($column['column_name'])
                 ->setDatatype(Column::TYPE_TIMESTAMP);
        }
        return count($columns);
    }

    /** {@inheritdoc} */
    public function setStrictMode()
    {
        // Force standard compliant escaping of single quotes ('', not \')
        $this->exec('SET backslash_quote TO off');
        // Treat backslashes literally (not as escape character)
        $this->exec('SET standard_conforming_strings TO on');
        // Keep special semantics of NULL, i.e. 'expr = NULL' always evaluates to FALSE
        $this->exec('SET transform_null_equals TO off');
        // Don't implicitly add missing columns to FROM clause (no longer supported with 9.0)
        if (version_compare($this->getServerVersion(), '9.0', '<')) {
            $this->exec('SET add_missing_from TO off');
        }
    }

    /** {@inheritdoc} */
    public function getNativeDatatype($type, $length=null, $cast=false)
    {
        switch ($type) {
            case Column::TYPE_TIMESTAMP:
                return 'TIMESTAMP(0)';
            case Column::TYPE_CLOB:
                return 'TEXT';
            case Column::TYPE_BLOB:
                return 'BYTEA';
            default:
                return parent::getNativeDatatype($type, $length, $cast);
        }
    }

    /** {@inheritdoc} */
    public function createTable($name, array $columns, $primaryKey=null)
    {
        $table = parent::createTable($name, $columns, $primaryKey);

        // CREATE TABLE does not set comments. Add them manually.
        foreach ($columns as $column) {
            if (is_array($column)) {
                $column = $this->createColumnFromArray($column);
            }
            $comment = $column->getComment();
            if ($comment) {
                $column->setTable($table);
                $column->setComment(null); // Cached value is invalid at this stage, reset it
                $column->setComment($comment);
            }
        }

        // Refresh cached table object
        $this->clearCache($name);
        return $this->getTable($name);
    }
}
