<?php
/**
 * Interface class for PostgreSQL
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
 * Interface class for PostgreSQL
 *
 * This class overrides methods with PostgreSQL-specific implementations.
 * @package NADA
 */
class Nada_Database_Pgsql extends Nada_Database
{

    /** {@inheritdoc} */
    protected $_tableSchema = 'public';

    /** {@inheritdoc} */
    public function isPgsql()
    {
        return true;
    }

    /** {@inheritdoc} */
    public function iLike()
    {
        return ' ILIKE ';
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
        if (version_compare($this->_link->getServerVersion(), '9.0', '<')) {
            $this->exec('SET add_missing_from TO off');
        }
    }

    /** {@inheritdoc} */
    public function getNativeDatatype($type, $length=null)
    {
        switch ($type) {
            case Nada::DATATYPE_CLOB:
                return 'TEXT';
            case Nada::DATATYPE_BLOB:
                return 'BYTEA';
            default:
                return parent::getNativeDatatype($type, $length);
        }
    }
}
